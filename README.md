# 🧩 PHP REST API for Asset Data Integration (MVC Version)

This PHP REST API integrates data between Laravel, Joget (MySQL), and BIM (SQLite3) systems.  
It follows an MVC structure for cleaner maintenance, scalability, and testing.  
The API supports reading Excel raw data (from Laravel), mapping columns to Joget tables, dynamically creating missing fields, linking BIM elements, and preventing duplicate asset records.  

# 📁 Project Structure (MVC Layout)

/JavaBridge/asset/  
├── /logs/  
│   └── api.log  
├── /config/  
│   └── database.php            # MySQL database connection using .env  
│  
├── /controllers/  
│   └── AssetController.php     # Handles all API requests and logic routing  
│  
├── /models/  
│   └── AssetModel.php          # Handles database operations and BIM/Excel logic  
│  
├── /helpers/  
│   └── utils.php               # Common helper functions (UUID, validation, etc.)  
│  
├── index.php                   # API entry point (router)  
├── .env                        # Environment configuration file  
├── composer.json               # PHP dependencies (Dotenv, etc.)  
├── /vendor/                    # Composer libraries (e.g., vlucas/phpdotenv)  
├── /uploadtmp/                 # Temporary upload directory (if needed)  
├── /uploads/                   # File storage (optional)  
└── README.md                   # Project documentation (this file)  


# ⚙️ Prerequisites

PHP ≥ 8.1 (PHP 8.3.26)  
MySQL ≥ 5.7 (used by Joget)  
Composer  
Joget DX 7 installed at C:\Joget-DX7-Enterprise\apache-tomcat-9.0.71\webapps\JavaBridge
Laravel backend for sending requests  

# 🧱 Installation

1️⃣ Clone / copy the API into your Joget installation:  

C:\Joget-DX7-Enterprise\apache-tomcat-9.0.71\webapps\JavaBridge\asset  

2️⃣ Install dependencies:  

cd C:\Joget-DX7-Enterprise\apache-tomcat-9.0.71\webapps\JavaBridge\asset  

composer install

✅ This will automatically install:  
vlucas/phpdotenv for environment configuration  
phpoffice/phpspreadsheet for Excel parsing  

3️⃣ Create .env file:  

APP_ENV=production  
APP_DEBUG=false  

# Database configuration
DB_HOST=127.0.0.1  
DB_PORT=3307  
DB_USER=root  
DB_PASSWORD=  
DB_NAME=jwdb  

APP_DEBUG=true  
APP_ENV=local  

# Base URL (for Laravel integration)
JOGET_API_URL=https://ams.reveronconsulting.com/JavaBridge/asset/index.php

4️⃣ Adjust PHP upload limits (if handling Excel uploads):  

upload_max_filesize=5000M  
post_max_size=5000M  
max_file_uploads=20  







