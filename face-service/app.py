from __future__ import annotations

import json
import os
import tempfile
import threading
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from deepface import DeepFace
from flask import Flask, jsonify, request

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"
INDEX_FILE = DATA_DIR / "face_index.json"
MODEL_NAME = os.getenv("FACE_MODEL", "Facenet512")
DETECTOR = os.getenv("FACE_DETECTOR", "opencv")
SERVICE_TOKEN = os.getenv("FACE_SERVICE_TOKEN", "change-this-face-service-token")
app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = 10 * 1024 * 1024
app.config["JSON_AS_ASCII"] = False
lock = threading.Lock()


def opencv_cascade_path() -> Path:
    return Path(cv2.data.haarcascades) / "haarcascade_frontalface_default.xml"


def validate_detector() -> None:
    if DETECTOR == "opencv" and not opencv_cascade_path().is_file():
        raise RuntimeError(
            "OpenCV ติดตั้งไม่สมบูรณ์: ไม่พบ haarcascade_frontalface_default.xml "
            "กรุณาปิดหน้าต่างนี้แล้วเปิด START_FACE_SERVICE.bat เพื่อซ่อมอัตโนมัติ"
        )


def authorized() -> bool:
    return request.headers.get("X-Service-Token", "") == SERVICE_TOKEN


def load_index() -> list[dict[str, Any]]:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    if not INDEX_FILE.exists():
        return []
    try:
        data = json.loads(INDEX_FILE.read_text(encoding="utf-8"))
        return data if isinstance(data, list) else []
    except (OSError, json.JSONDecodeError):
        return []


def save_index(records: list[dict[str, Any]]) -> None:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    temp = INDEX_FILE.with_suffix(".tmp")
    temp.write_text(json.dumps(records, ensure_ascii=False), encoding="utf-8")
    temp.replace(INDEX_FILE)


def embeddings(path: str) -> list[list[float]]:
    faces = DeepFace.represent(
        img_path=path,
        model_name=MODEL_NAME,
        detector_backend=DETECTOR,
        enforce_detection=True,
        align=True,
    )
    return [
        [float(value) for value in face.get("embedding", [])]
        for face in faces
        if isinstance(face.get("embedding"), list) and face.get("embedding")
    ]


def similarity(left: list[float], right: list[float]) -> float:
    a = np.asarray(left, dtype=np.float32)
    b = np.asarray(right, dtype=np.float32)
    if a.shape != b.shape or a.size == 0:
        return 0.0
    denominator = float(np.linalg.norm(a) * np.linalg.norm(b))
    return 0.0 if denominator == 0 else float(np.dot(a, b) / denominator)


@app.get("/health")
def health():
    with lock:
        count = len(load_index())
    return jsonify(
        {
            "status": "ok",
            "model": MODEL_NAME,
            "detector": DETECTOR,
            "opencv": cv2.__version__,
            "cascade_ready": opencv_cascade_path().is_file(),
            "indexed_faces": count,
        }
    )


@app.post("/index")
def index_photo():
    if not authorized():
        return jsonify({"message": "unauthorized"}), 401
    payload = request.get_json(silent=True) or {}
    try:
        event_id = int(payload.get("event_id", 0))
        photo_id = int(payload.get("photo_id", 0))
    except (TypeError, ValueError):
        return jsonify({"message": "รหัสไม่ถูกต้อง"}), 400
    image_path = str(payload.get("path", ""))
    if event_id <= 0 or photo_id <= 0 or not os.path.isfile(image_path):
        return jsonify({"message": "ข้อมูลรูปไม่ถูกต้อง"}), 400
    try:
        items = embeddings(image_path)
    except Exception as exc:
        return jsonify({"message": f"ไม่พบใบหน้าหรือประมวลผลไม่สำเร็จ: {exc}"}), 422
    with lock:
        records = [record for record in load_index() if int(record.get("photo_id", 0)) != photo_id]
        records.extend(
            {"event_id": event_id, "photo_id": photo_id, "embedding": item}
            for item in items
        )
        save_index(records)
    return jsonify({"photo_id": photo_id, "faces": len(items)})


@app.post("/search")
def search():
    if not authorized():
        return jsonify({"message": "unauthorized"}), 401
    try:
        event_id = int(request.form.get("event_id", "0"))
        threshold = float(request.form.get("threshold", "0.72"))
    except (TypeError, ValueError):
        return jsonify({"message": "ค่าค้นหาไม่ถูกต้อง"}), 400
    threshold = min(max(threshold, 0.50), 0.95)
    selfie = request.files.get("selfie")
    if event_id <= 0 or selfie is None or not selfie.filename:
        return jsonify({"message": "ไม่พบรูปใบหน้า"}), 400
    suffix = Path(selfie.filename).suffix.lower()
    suffix = suffix if suffix in {".jpg", ".jpeg", ".png", ".webp"} else ".jpg"
    path = ""
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as temp:
            selfie.save(temp)
            path = temp.name
        query = embeddings(path)
        if len(query) != 1:
            return jsonify({"message": "กรุณาใช้รูปที่มีใบหน้าผู้ค้นหาเพียงคนเดียว"}), 422
        with lock:
            records = [
                record
                for record in load_index()
                if int(record.get("event_id", 0)) == event_id
            ]
        best: dict[int, float] = {}
        for record in records:
            score = similarity(query[0], record.get("embedding", []))
            photo_id = int(record.get("photo_id", 0))
            if photo_id > 0 and score >= threshold:
                best[photo_id] = max(best.get(photo_id, 0.0), score)
        matches = [
            {"photo_id": photo_id, "similarity": round(score, 6)}
            for photo_id, score in best.items()
        ]
        matches.sort(key=lambda item: item["similarity"], reverse=True)
        return jsonify({"matches": matches, "threshold": threshold})
    except Exception as exc:
        return jsonify({"message": f"ค้นหาใบหน้าไม่สำเร็จ: {exc}"}), 422
    finally:
        if path:
            try:
                os.remove(path)
            except OSError:
                pass


if __name__ == "__main__":
    validate_detector()
    app.run(host="127.0.0.1", port=5055, debug=False)
