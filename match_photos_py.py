import os
import mysql.connector
from mysql.connector import errorcode

# Configuration
DB_CONFIG = {
    'user': 'root',
    'password': 'root',
    'database': 'local',
    'unix_socket': '/Users/nicolasibarra/Library/Application Support/Local/run/XqvmEtSc4/mysql/mysqld.sock',
    # 'host': 'localhost', # Not needed if unix_socket is used
    'charset': 'utf8',
}

TABLE_PREFIX = 'wp_'
BASE_DIR = os.path.dirname(os.path.abspath(__file__)) + '/assets/politician_profile_photos'

OFFICE_MAP = {
    'senador': 'SENADOR',
    'diputado': 'DIPUTADO',
    'alcalde': 'ALCALDE',
    'gobernador': 'GOBERNADOR',
    'concejal': 'CONCEJAL',
    'core': 'CORE'
}

YEARS = ['2024', '2017', '2021']

def match_photos():
    try:
        cnx = mysql.connector.connect(**DB_CONFIG)
        cursor = cnx.cursor(dictionary=True, buffered=True)
        print(f"Connected to database: {DB_CONFIG['database']}")
    except mysql.connector.Error as err:
        print(f"Error connecting to database: {err}")
        return

    for year in YEARS:
        year_dir = os.path.join(BASE_DIR, year)
        if not os.path.exists(year_dir):
            print(f"Directory not found: {year_dir}")
            continue

        print(f"Processing Year: {year}")
        files = os.listdir(year_dir)
        
        for filename in files:
            if not filename.endswith('.jpeg'):
                continue
            
            process_file(cursor, cnx, year, filename)
    
    cnx.close()

def process_file(cursor, cnx, year, filename):
    name_part = os.path.splitext(filename)[0]
    
    # Remove _win
    if name_part.endswith('_win'):
        name_part = name_part[:-4]
    
    # Extract office
    office_key = ''
    for k in OFFICE_MAP.keys():
        if name_part.endswith(k):
            office_key = k
            break
    
    if not office_key:
        print(f"Skipping (unknown office): {filename}")
        return

    clean_name_str = name_part[:-(len(office_key) + 1)] # remove _office
    parts = clean_name_str.split('_')
    
    given = ''
    paternal = ''
    maternal = ''

    if len(parts) >= 3:
        maternal = parts.pop()
        paternal = parts.pop()
        given = ' '.join(parts)
    elif len(parts) == 2:
        paternal = parts.pop()
        given = ' '.join(parts)
    else:
        print(f"Skipping invalid name format: {clean_name_str}")
        return

    person_id = find_person(cursor, given, paternal, maternal)
    
    # Retry for composite surnames (e.g. San Martin) split as Paternal Maternal
    if not person_id and maternal:
        composite_paternal = f"{paternal} {maternal}"
        person_id = find_person(cursor, given, composite_paternal, '')
        if person_id:
             print(f"  Matched via composite surname: {composite_paternal}")

    if not person_id:
        print(f"No match for person: {given} {paternal} {maternal} ({filename})")
        return

    office_code = OFFICE_MAP[office_key]
    candidacy_id = find_candidacy(cursor, person_id, year, office_code)

    if candidacy_id:
        rel_path = f"politician_profile_photos/{year}/{filename}"
        update_candidacy(cursor, cnx, candidacy_id, rel_path)
        print(f"Updated match: {given} {paternal} -> ID {candidacy_id}")
    else:
        print(f"No candidacy for Person ID {person_id} in {year} as {office_code}")

def find_person(cursor, given, paternal, maternal):
    if maternal:
        query = f"SELECT id FROM {TABLE_PREFIX}politeia_people WHERE given_names LIKE %s AND paternal_surname LIKE %s AND maternal_surname LIKE %s"
        cursor.execute(query, (given + '%', paternal, maternal))
        result = cursor.fetchone()
        if result:
            return result['id']
    else:
        # 2-part name match
        query = f"SELECT id FROM {TABLE_PREFIX}politeia_people WHERE given_names LIKE %s AND paternal_surname LIKE %s"
        cursor.execute(query, (given + '%', paternal))
        results = cursor.fetchall()
        if len(results) == 1:
            return results[0]['id']
        elif len(results) > 1:
            print(f"Ambiguous match for {given} {paternal} (Found {len(results)})")
            return results[0]['id'] # Pick first
    
    return None

def find_candidacy(cursor, person_id, year, office_code):
    query = f"""
        SELECT c.id 
        FROM {TABLE_PREFIX}politeia_candidacies c
        JOIN {TABLE_PREFIX}politeia_elections e ON c.election_id = e.id
        JOIN {TABLE_PREFIX}politeia_offices o ON e.office_id = o.id
        WHERE c.person_id = %s
        AND YEAR(e.election_date) = %s
        AND o.code = %s
        LIMIT 1
    """
    cursor.execute(query, (person_id, year, office_code))
    result = cursor.fetchone()
    if result:
        return result['id']
    return None

def update_candidacy(cursor, cnx, cid, path):
    query = f"UPDATE {TABLE_PREFIX}politeia_candidacies SET profile_photo_url = %s WHERE id = %s"
    cursor.execute(query, (path, cid))
    cnx.commit()

if __name__ == "__main__":
    match_photos()
