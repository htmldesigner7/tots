# QA scripts (development only; Python 3 + Playwright)

* `lvcmp.py`: serve the original HTML on port 8765 (`python3 -m http.server 8765` in the static site folder) and the Laravel app (set `LAR=http://127.0.0.1:8000`). It screenshots every page at several widths and reports pixel differences.
* `admin_e2e.py`: browser walkthrough of the main CMS workflows. It **changes content**, so use it only on a freshly seeded local copy. Set `CMS_E2E_EMAIL` and `CMS_E2E_PASSWORD` for a test account first.
