import os
import mysql.connector

# Configuration (same as before)
DB_CONFIG = {
    'user': 'root',
    'password': 'root',
    'database': 'local',
    'unix_socket': '/Users/nicolasibarra/Library/Application Support/Local/run/XqvmEtSc4/mysql/mysqld.sock',
    'charset': 'utf8',
}
TABLE_PREFIX = 'wp_'

def verify():
    cnx = mysql.connector.connect(**DB_CONFIG)
    cursor = cnx.cursor(dictionary=True)

    targets = [
        ('Catalina', 'San Martin'),
        ('Jaime', 'Bellolio'),
        ('Matias', 'Toledo')
    ]

    for given, paternal in targets:
        # Find Matches
        print(f"Checking {given} {paternal}...")
        query = f"""
            SELECT p.given_names, p.paternal_surname, c.profile_photo_url, o.code as office
            FROM {TABLE_PREFIX}politeia_people p
            JOIN {TABLE_PREFIX}politeia_candidacies c ON p.id = c.person_id
            JOIN {TABLE_PREFIX}politeia_elections e ON c.election_id = e.id
            JOIN {TABLE_PREFIX}politeia_offices o ON e.office_id = o.id
            WHERE p.given_names LIKE %s AND p.paternal_surname LIKE %s
            AND YEAR(e.election_date) = 2024
        """
        cursor.execute(query, (given + '%', paternal + '%'))
        results = cursor.fetchall()
        
        if not results:
             print("  No 2024 candidacy found.")
        else:
             for r in results:
                 print(f"  Found: {r['given_names']} {r['paternal_surname']} ({r['office']})")
                 print(f"  URL: {r['profile_photo_url']}")

    cnx.close()

if __name__ == "__main__":
    verify()
