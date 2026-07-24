from __future__ import annotations

import json
import os
import tempfile
import threading
from pathlib import Path
from typing import Any

import numpy as np
from deepface import DeepFace
from flask import Flask, jsonify, request

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"
INDEX_FILE = DATA_DIR / "face_index.json"
MODEL_NAME = os.getenv("FACE_MODEL", "Facenet512")
DETECTOR_BACKEND = os.getenv("FACE_DETECTOR", "opencv")
MAX_UPLOAD_BYTES = 10 * 1024 * 1024

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = MAX_UPLOAD_BYTES
app.config["JSON_AS_ASCII"] = False
index_lock = threading.Lock()


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
    temporary = INDEX_FILE.with_suffix(".tmp")
    temporary.write_text(json.dumps(records, ensure_ascii=False), encoding="utf-8")
    temporary.replace(INDEX_FILE)


def embeddings_from_image(path: str) -> list[list[float]]:
    faces = DeepFace.represent(
        img_path=path,
        model_name=MODEL_NAME,
        detector_backend=DETECTOR_BACKEND,
        enforce_detection=True,
        align=True,
    )
    embeddings: list[list[float]] = []
    for face in faces:
        embedding = face.get("embedding")
        if isinstance(embedding, list) and embedding:
            embeddings.append([float(value) for value in embedding])
    return embeddings


def cosine_similarity(left: list[float], right: list[float]) -> float:
    vector_left = np.asarray(left, dtype=np.float32)
    vector_right = np.asarray(right, dtype=np.float32)
    if vector_left.shape != vector_right.shape or vector_left.size == 0:
        return 0.0
    denominator = float(np.linalg.norm(vector_left) * np.linalg.norm(vector_right))
    if denominator == 0.0:
        return 0.0
    return float(np.dot(vector_left, vector_right) / denominator)


@app.get("/health")
def health():
    with index_lock:
        records = load_index()
    return jsonify({"status": "ok", "model": MODEL_NAME, "indexed_faces": len(records)})


@app.post("/index")
def index_photo():
    payload = request.get_json(silent=True) or {}
    try:
        event_id = int(payload.get("event_id", 0))
        photo_id = int(payload.get("photo_id", 0))
    except (TypeError, ValueError):
        return jsonify({"message": "รหัสกิจกรรมหรือรูปไม่ถูกต้อง"}), 400

    image_path = str(payload.get("path", ""))
    if event_id <= 0 or photo_id <= 0 or not image_path or not os.path.isfile(image_path):
        return jsonify({"message": "ข้อมูลรูปสำหรับทำดัชนีไม่ถูกต้อง"}), 400

    try:
        embeddings = embeddings_from_image(image_path)
    except Exception as exc:
        return jsonify({"message": f"ไม่พบใบหน้าที่ตรวจจับได้หรือประมวลผลไม่สำเร็จ: {exc}"}), 422

    with index_lock:
        records = [
            record
            for record in load_index()
            if int(record.get("photo_id", 0)) != photo_id
        ]
        for embedding in embeddings:
            records.append({"event_id": event_id, "photo_id": photo_id, "embedding": embedding})
        save_index(records)

    return jsonify({"photo_id": photo_id, "faces": len(embeddings)})


@app.post("/search")
def search_faces():
    try:
        event_id = int(request.form.get("event_id", "0"))
        threshold = float(request.form.get("threshold", "0.72"))
    except (TypeError, ValueError):
        return jsonify({"message": "ค่ากิจกรรมหรือความเหมือนไม่ถูกต้อง"}), 400

    threshold = min(max(threshold, 0.50), 0.95)
    selfie = request.files.get("selfie")
    if event_id <= 0 or selfie is None or not selfie.filename:
        return jsonify({"message": "ไม่พบรูปใบหน้าสำหรับค้นหา"}), 400

    suffix = Path(selfie.filename).suffix.lower()
    if suffix not in {".jpg", ".jpeg", ".png", ".webp"}:
        suffix = ".jpg"

    temporary_path = ""
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as temporary:
            selfie.save(temporary)
            temporary_path = temporary.name

        query_embeddings = embeddings_from_image(temporary_path)
        if len(query_embeddings) != 1:
            return jsonify({"message": "กรุณาใช้รูปที่มีใบหน้าของผู้ค้นหาเพียงคนเดียว"}), 422
        query_embedding = query_embeddings[0]

        best_by_photo: dict[int, float] = {}
        with index_lock:
            event_records = [
                record
                for record in load_index()
                if int(record.get("event_id", 0)) == event_id
            ]

        for record in event_records:
            similarity = cosine_similarity(query_embedding, record.get("embedding", []))
            photo_id = int(record.get("photo_id", 0))
            if photo_id > 0 and similarity >= threshold:
                best_by_photo[photo_id] = max(best_by_photo.get(photo_id, 0.0), similarity)

        matches = [
            {"photo_id": photo_id, "similarity": round(similarity, 6)}
            for photo_id, similarity in best_by_photo.items()
        ]
        matches.sort(key=lambda item: item["similarity"], reverse=True)
        return jsonify({"matches": matches, "threshold": threshold})
    except Exception as exc:
        return jsonify({"message": f"ไม่พบใบหน้าที่ชัดเจนหรือค้นหาไม่สำเร็จ: {exc}"}), 422
    finally:
        if temporary_path:
            try:
                os.remove(temporary_path)
            except OSError:
                pass


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5055, debug=False)
