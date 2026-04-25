import os

API_KEY = os.getenv("GLM_API_KEY", "<API-KEY>")
MODEL = "ilmu-glm-5.1"
BASE_URL = "https://api.ilmu.ai/v1"


# MySQL — matches cats/config.php
MYSQL_HOST = os.getenv("MYSQL_HOST", "localhost")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", 3306))
MYSQL_USER = os.getenv("MYSQL_USER", "root")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD", "")
MYSQL_DATABASE = os.getenv("MYSQL_DATABASE", "cats_db")
