# Dashboard container — for Render / Railway / Fly / any Docker host.
# (Streamlit Community Cloud does NOT need this; it uses requirements.txt.)
FROM python:3.11-slim

WORKDIR /app
# Copy the whole repo so the engine (../src) is importable from dashboard/app.py.
COPY . /app

RUN pip install --no-cache-dir -r dashboard/requirements.txt

EXPOSE 8501
# WP_* and DASHBOARD_PASSWORD are provided as environment variables at run time.
CMD ["streamlit", "run", "dashboard/app.py", \
     "--server.port=8501", "--server.address=0.0.0.0", "--server.headless=true"]
