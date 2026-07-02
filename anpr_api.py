import os
import sys
import logging
from datetime import datetime
from flask import Flask, request, jsonify
import mysql.connector

# Initialize Flask application
app = Flask(__name__)

# Setup logging
log_dir = os.path.join(os.path.dirname(__file__), 'storage', 'logs')
os.makedirs(log_dir, exist_ok=True)
log_file = os.path.join(log_dir, 'anpr_debug.log')

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(log_file),
        logging.StreamHandler(sys.stdout)
    ]
)

# Parse .env file manually to avoid dependency on dotenv library
def load_env():
    env_vars = {}
    env_path = os.path.join(os.path.dirname(__file__), '.env')
    if os.path.exists(env_path):
        with open(env_path, 'r') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                if '=' in line:
                    key, value = line.split('=', 1)
                    env_vars[key.strip()] = value.strip().strip('"').strip("'")
    return env_vars

ENV = load_env()

# Database Config from .env with fallbacks
DB_HOST = ENV.get('DB_HOST', '127.0.0.1')
DB_PORT = int(ENV.get('DB_PORT', 3306))
DB_USER = ENV.get('DB_USERNAME', 'root')
DB_PASSWORD = ENV.get('DB_PASSWORD', '')
DB_NAME = ENV.get('DB_DATABASE', 'v4_modomines1')

def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME
    )

def init_db():
    """Ensure the target database and anpr_logs table exist."""
    conn = None
    cursor = None
    try:
        # Connect without database first to ensure database exists (if needed)
        conn = mysql.connector.connect(
            host=DB_HOST,
            port=DB_PORT,
            user=DB_USER,
            password=DB_PASSWORD
        )
        cursor = conn.cursor()
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS `{DB_NAME}`")
        conn.commit()
        cursor.close()
        conn.close()

        # Connect to specific database
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Create anpr_logs table
        create_table_query = """
        CREATE TABLE IF NOT EXISTS `anpr_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `plate_number` VARCHAR(50) NOT NULL,
            `confidence` INT DEFAULT NULL,
            `vehicle_color` VARCHAR(50) DEFAULT NULL,
            `vehicle_type` VARCHAR(50) DEFAULT NULL,
            `camera_ip` VARCHAR(50) DEFAULT NULL,
            `camera_mac` VARCHAR(50) DEFAULT NULL,
            `captured_at` DATETIME NOT NULL,
            `photo_path` VARCHAR(255) DEFAULT NULL,
            `plate_photo_path` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        """
        cursor.execute(create_table_query)
        conn.commit()
        logging.info("Database table 'anpr_logs' initialized successfully.")
    except Exception as e:
        logging.error(f"Failed to initialize database: {e}")
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

# Initialize DB on startup
init_db()

@app.route('/', methods=['GET', 'POST'])
@app.route('/api/anpr', methods=['GET', 'POST'])
def receive_anpr():
    # Check if this is a diagnostic GET request (no plate details provided in query params)
    has_get_data = any(k in request.args for k in ('PlateNum', 'plate', 'license', 'plate_number', 'Plate'))
    if request.method == 'GET' and not has_get_data:
        return jsonify({
            "status": "online",
            "message": "TVT ANPR API Service is running.",
            "timestamp": datetime.now().isoformat()
        }), 200

    logging.info(f"Incoming ANPR {request.method} Request received.")
    
    # Log headers and query params for debugging
    logging.info(f"Headers: {dict(request.headers)}")
    logging.info(f"Args: {dict(request.args)}")
    logging.info(f"Form Fields: {list(request.form.keys())}")
    logging.info(f"Files: {list(request.files.keys())}")

    # Extract fields (trying Form data first, then GET query parameters, then fallback to JSON)
    plate_num = (request.form.get('PlateNum') or request.form.get('plate') or request.form.get('license') or request.form.get('Plate') or
                 request.args.get('PlateNum') or request.args.get('plate') or request.args.get('license') or request.args.get('plate_number') or request.args.get('Plate'))
    
    confidence = (request.form.get('Confidence') or request.form.get('confidence') or
                  request.args.get('Confidence') or request.args.get('confidence'))
    
    vehicle_color = (request.form.get('VehicleColor') or request.form.get('vehicleColor') or request.form.get('color') or
                     request.args.get('VehicleColor') or request.args.get('vehicleColor') or request.args.get('color'))
    
    vehicle_type = (request.form.get('VehicleType') or request.form.get('vehicleType') or request.form.get('type') or
                    request.args.get('VehicleType') or request.args.get('vehicleType') or request.args.get('type'))
    
    camera_mac = (request.form.get('Mac') or request.form.get('mac') or
                  request.args.get('Mac') or request.args.get('mac'))
    
    camera_ip = (request.form.get('IP') or request.form.get('ip') or 
                 request.args.get('IP') or request.args.get('ip') or request.remote_addr)
    
    captured_time_str = (request.form.get('Time') or request.form.get('time') or request.form.get('dateTime') or
                         request.args.get('Time') or request.args.get('time') or request.args.get('dateTime'))

    # Fallback to parsing JSON if camera sent JSON payload instead of form data/GET params
    if request.is_json:
        json_data = request.get_json()
        logging.info(f"JSON Payload: {json_data}")
        if json_data:
            plate_num = plate_num or json_data.get('PlateNum') or json_data.get('plate') or json_data.get('licensePlate') or json_data.get('Plate')
            confidence = confidence or json_data.get('Confidence') or json_data.get('confidence')
            vehicle_color = vehicle_color or json_data.get('VehicleColor') or json_data.get('vehicleColor') or json_data.get('color')
            vehicle_type = vehicle_type or json_data.get('VehicleType') or json_data.get('vehicleType') or json_data.get('type')
            camera_mac = camera_mac or json_data.get('Mac') or json_data.get('mac')
            camera_ip = camera_ip or json_data.get('IP') or json_data.get('ip')
            captured_time_str = captured_time_str or json_data.get('Time') or json_data.get('time')

    # If it's a nested uploadinfo JSON inside form-data (sometimes sent by some TVT API versions)
    upload_info_str = request.form.get('uploadinfo') or request.form.get('info')
    if upload_info_str:
        import json
        try:
            info_json = json.loads(upload_info_str)
            logging.info(f"Decoded Nested uploadinfo: {info_json}")
            plate_num = plate_num or info_json.get('PlateNum') or info_json.get('plate')
            confidence = confidence or info_json.get('Confidence') or info_json.get('confidence')
            vehicle_color = vehicle_color or info_json.get('VehicleColor') or info_json.get('vehicleColor')
            vehicle_type = vehicle_type or info_json.get('VehicleType') or info_json.get('vehicleType')
            camera_mac = camera_mac or info_json.get('Mac') or info_json.get('mac')
            camera_ip = camera_ip or info_json.get('IP') or info_json.get('ip')
            captured_time_str = captured_time_str or info_json.get('Time') or info_json.get('time')
        except Exception as ex:
            logging.warning(f"Failed to parse uploadinfo string: {ex}")

    # Set default values if not found
    if not plate_num:
        plate_num = "UNKNOWN"
        logging.warning("Plate Number not found in payload.")
        
    try:
        confidence = int(confidence) if confidence else None
    except ValueError:
        confidence = None

    captured_at = datetime.now()
    if captured_time_str:
        for fmt in ('%Y-%m-%d %H:%M:%S', '%Y-%m-%dT%H:%M:%S', '%Y/%m/%d %H:%M:%S'):
            try:
                captured_at = datetime.strptime(captured_time_str, fmt)
                break
            except ValueError:
                continue

    # File Upload Directory
    upload_rel_dir = os.path.join('uploads', 'anpr')
    upload_abs_dir = os.path.join(os.path.dirname(__file__), 'public', upload_rel_dir)
    os.makedirs(upload_abs_dir, exist_ok=True)

    photo_path = None
    plate_photo_path = None
    timestamp_slug = datetime.now().strftime('%Y%m%d_%H%M%S')
    plate_slug = "".join(x for x in plate_num if x.isalnum())

    # Save Panoramic / Scene Photo
    # TVT usually sends full image in 'pic', 'imageFile', 'sceneImage', etc.
    pan_file = request.files.get('pic') or request.files.get('imageFile') or request.files.get('image')
    if pan_file:
        filename = f"{timestamp_slug}_{plate_slug}_full.jpg"
        pan_filepath = os.path.join(upload_abs_dir, filename)
        pan_file.save(pan_filepath)
        photo_path = f"/uploads/anpr/{filename}"
        logging.info(f"Saved panoramic image to {photo_path}")

    # Save Cropped Plate Photo
    # TVT usually sends cropped plate image in 'platePic', 'plateImage', etc.
    crop_file = request.files.get('platePic') or request.files.get('plateImage') or request.files.get('cropPic')
    if crop_file:
        filename = f"{timestamp_slug}_{plate_slug}_plate.jpg"
        crop_filepath = os.path.join(upload_abs_dir, filename)
        crop_file.save(crop_filepath)
        plate_photo_path = f"/uploads/anpr/{filename}"
        logging.info(f"Saved plate image to {plate_photo_path}")

    # Fallback: Save any uploaded files if the camera used non-standard keys
    if not photo_path and request.files:
        for key, file in request.files.items():
            if key not in ['pic', 'imageFile', 'image', 'platePic', 'plateImage', 'cropPic']:
                filename = f"{timestamp_slug}_{plate_slug}_{key}.jpg"
                filepath = os.path.join(upload_abs_dir, filename)
                file.save(filepath)
                if not photo_path:
                    photo_path = f"/uploads/anpr/{filename}"
                else:
                    plate_photo_path = f"/uploads/anpr/{filename}"
                logging.info(f"Saved fallback file {key} to {filename}")

    # Insert Record into MySQL
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        insert_query = """
        INSERT INTO `anpr_logs` (
            `plate_number`, `confidence`, `vehicle_color`, `vehicle_type`, 
            `camera_ip`, `camera_mac`, `captured_at`, `photo_path`, `plate_photo_path`
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
        """
        cursor.execute(insert_query, (
            plate_num.upper(), confidence, vehicle_color, vehicle_type,
            camera_ip, camera_mac, captured_at, photo_path, plate_photo_path
        ))
        conn.commit()
        logging.info(f"Successfully logged plate {plate_num.upper()} to DB.")
    except Exception as e:
        logging.error(f"Failed to save plate log to database: {e}")
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()

    return jsonify({"status": "success", "message": "ANPR event logged successfully"}), 200

if __name__ == '__main__':
    # Listen on all interfaces on port 8082
    # You can change the port in the camera setup to match this.
    app.run(host='0.0.0.0', port=8082, debug=False)
