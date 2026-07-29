# Vendored Tesseract.js (screenshot gear import)

Self-hosted so Arcadia makes **zero third-party requests** for OCR.

| Path | Source |
|------|--------|
| `tesseract.min.js`, `worker.min.js` | [tesseract.js](https://github.com/naptha/tesseract.js) 5.1.1 (Apache-2.0) |
| `core/*.wasm.js` | [tesseract.js-core](https://github.com/naptha/tesseract.js-core) 5.1.1 (Apache-2.0) |
| `lang/eng.traineddata.gz` | [tessdata](https://github.com/tesseract-ocr/tessdata) 4.0.0 via projectnaptha |

To refresh: re-copy those files from the matching npm versions and re-download
`https://tessdata.projectnaptha.com/4.0.0/eng.traineddata.gz` into `lang/`.
