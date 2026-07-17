#!/bin/bash
# Descarga los modelos de face-api.js necesarios para el reconocimiento facial
# Ejecutar desde la raíz del proyecto Laravel: bash descargar_modelos.sh
set -e
BASE="https://raw.githubusercontent.com/vladmandic/face-api/master/model"
DEST="public/models"
mkdir -p "$DEST"
ARCHIVOS=(
  tiny_face_detector_model-weights_manifest.json
  tiny_face_detector_model.bin
  face_landmark_68_model-weights_manifest.json
  face_landmark_68_model.bin
  face_recognition_model-weights_manifest.json
  face_recognition_model.bin
)
for f in "${ARCHIVOS[@]}"; do
  echo "Descargando $f ..."
  curl -sL "$BASE/$f" -o "$DEST/$f"
done
echo "Modelos descargados en $DEST"
