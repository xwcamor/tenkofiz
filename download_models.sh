#!/bin/bash
# Downloads the face-api.js models required for facial recognition
# Run from the Laravel project root: bash download_models.sh
set -e
BASE="https://raw.githubusercontent.com/vladmandic/face-api/master/model"
DEST="public/models"
mkdir -p "$DEST"
FILES=(
  tiny_face_detector_model-weights_manifest.json
  tiny_face_detector_model.bin
  face_landmark_68_model-weights_manifest.json
  face_landmark_68_model.bin
  face_recognition_model-weights_manifest.json
  face_recognition_model.bin
)
for f in "${FILES[@]}"; do
  echo "Downloading $f ..."
  curl -sL "$BASE/$f" -o "$DEST/$f"
done
echo "Models downloaded to $DEST"
