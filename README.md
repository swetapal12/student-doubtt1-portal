# Student Doubt Portal — Streamlit Edition

A Python/Streamlit conversion of the supplied PHP Student Doubt Portal.

## Run locally

```bash
pip install -r requirements.txt
streamlit run app.py
```

## Demo teacher login
- Email: `teacher@portal.local`
- Password: `Teacher@123`

Students can register from the Student Registration tab.

## Deploy on Streamlit Community Cloud
1. Upload `app.py`, `requirements.txt`, and this README to a GitHub repository (keep the files in the repository root).
2. Go to https://share.streamlit.io/ and sign in with GitHub.
3. Choose **Create app**, select the repository and branch, and set the main file path to `app.py`.
4. Select **Deploy**.

## Database note
This starter uses SQLite (`student_doubt_portal.db`). On Streamlit Community Cloud, local files may not be durable across app restarts/redeployments. For a real public portal, use a hosted database and change the demo teacher password / add stronger production security before sharing publicly.
