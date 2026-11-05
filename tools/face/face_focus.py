#!/usr/bin/env python3
# 找照片裡的臉，算出裁切焦點（愛的見證照片；熊叔叔 2026-11-05「愛的見證照片是否可以判斷臉」「好安裝」）
# 用法：face_focus.py <圖檔>   → 印一行 JSON：{"faces": N, "x": 0-100, "y": 0-100}；找不到臉 x/y 是 null
# 模型：OpenCV YuNet（opencv_zoo，face_detection_yunet_2023mar.onnx，放在本檔同一層的 face/ 裡）
import json, os, sys
import cv2

MODEL = os.path.join(os.path.dirname(os.path.abspath(__file__)), "face", "face_detection_yunet_2023mar.onnx")
MAX_SIDE = 960        # 大圖先縮小再找，快、也夠準
MIN_SCORE = 0.6       # 先放寬找（婚紗照臉常側著，#3 親吻側臉只有 0.78）
TOP_GAP = 0.03        # 只留跟最高分差 0.03 以內的臉：主角最清楚；背景賓客、卡通圖案分數較低（#2 獅子 0.84 vs 新人 0.94）

def main(path: str) -> dict:
    img = cv2.imread(path)
    if img is None:
        return {"faces": 0, "x": None, "y": None, "error": "讀不到圖"}
    h, w = img.shape[:2]
    scale = min(1.0, MAX_SIDE / max(h, w))
    if scale < 1.0:
        img = cv2.resize(img, (int(w * scale), int(h * scale)))
    sh, sw = img.shape[:2]
    det = cv2.FaceDetectorYN.create(MODEL, "", (sw, sh), MIN_SCORE, 0.3, 5000)
    _, faces = det.detect(img)
    if faces is None or len(faces) == 0:
        return {"faces": 0, "x": None, "y": None}
    # 焦點＝分數最高那一群臉的中心，用臉的大小加權（2026-11-05 用 4 張示範見證照片校準：跟人工判斷差 0～5%）
    best = max(f[14] for f in faces)
    keep = [(f, f[2] * f[3]) for f in faces if f[14] >= best - TOP_GAP]
    tot = sum(a for _, a in keep)
    cx = sum((f[0] + f[2] / 2) * a for f, a in keep) / tot
    cy = sum((f[1] + f[3] / 2) * a for f, a in keep) / tot
    return {"faces": len(keep), "x": round(cx / sw * 100), "y": round(cy / sh * 100)}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"error": "用法：face_focus.py <圖檔>"}))
        sys.exit(2)
    print(json.dumps(main(sys.argv[1]), ensure_ascii=False))
