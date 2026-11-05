# 愛的見證照片自動找臉（OpenCV YuNet）

熊叔叔 2026-11-05 TG「愛的見證照片是否可以判斷臉，而不是被切掉」「好安裝」。
投稿見證時，網站呼叫 `face_focus.py` 找臉，存 `love_stories.photo_focus_x/y`，列表、封面頁裁照片時以臉為中心。
主機沒裝、找不到臉、出錯或超過 15 秒，都不影響投稿：焦點留空、用預設（上方 30%），管理員在「審核愛的見證」點臉可以改。

## 安裝（要 sudo；cora-lab 2026-11-05 裝過）

    sudo mkdir -p /opt/loveuno-face/face
    sudo python3 -m venv /opt/loveuno-face/venv
    sudo /opt/loveuno-face/venv/bin/pip install opencv-python-headless
    sudo curl -L -o /opt/loveuno-face/face/face_detection_yunet_2023mar.onnx \
      https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx
    # sha256 8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4
    sudo cp tools/face/face_focus.py /opt/loveuno-face/face_focus.py
    sudo chmod -R a+rX /opt/loveuno-face

授權：OpenCV Apache 2.0、YuNet 模型 MIT（opencv_zoo），免費。

## 驗證、補舊照片

    sudo -u www-data /opt/loveuno-face/venv/bin/python /opt/loveuno-face/face_focus.py <照片>   # 印 {"faces":…, "x":…, "y":…}
    sudo -u www-data php artisan love-stories:find-faces --dry-run      # 先看結果
    sudo -u www-data php artisan love-stories:find-faces                # 只補還沒設焦點的（--all 連點過的也重找）

路徑不一樣就在 .env 設 `LOVEUNO_FACE_PYTHON`、`LOVEUNO_FACE_SCRIPT`。
