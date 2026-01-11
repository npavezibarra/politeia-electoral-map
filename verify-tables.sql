-- Verify all Politeia tables exist
-- Run this in your MySQL client or phpMyAdmin

USE local;

-- Show all politeia tables
SHOW TABLES LIKE 'wp_politeia_%';

-- Count records in each table
SELECT 'people' as table_name, COUNT(*) as count FROM wp_politeia_people
UNION ALL
SELECT 'political_organizations', COUNT(*) FROM wp_politeia_political_organizations
UNION ALL
SELECT 'jurisdictions', COUNT(*) FROM wp_politeia_jurisdictions
UNION ALL
SELECT 'offices', COUNT(*) FROM wp_politeia_offices
UNION ALL
SELECT 'office_terms', COUNT(*) FROM wp_politeia_office_terms
UNION ALL
SELECT 'party_memberships', COUNT(*) FROM wp_politeia_party_memberships
UNION ALL
SELECT 'jurisdiction_populations', COUNT(*) FROM wp_politeia_jurisdiction_populations
UNION ALL
SELECT 'jurisdiction_budgets', COUNT(*) FROM wp_politeia_jurisdiction_budgets
UNION ALL
SELECT 'elections', COUNT(*) FROM wp_politeia_elections
UNION ALL
SELECT 'election_coalitions', COUNT(*) FROM wp_politeia_election_coalitions
UNION ALL
SELECT 'election_coalition_members', COUNT(*) FROM wp_politeia_election_coalition_members
UNION ALL
SELECT 'election_lista_assignments', COUNT(*) FROM wp_politeia_election_lista_assignments
UNION ALL
SELECT 'election_results', COUNT(*) FROM wp_politeia_election_results
UNION ALL
SELECT 'candidacies', COUNT(*) FROM wp_politeia_candidacies
UNION ALL
SELECT 'party_leanings', COUNT(*) FROM wp_politeia_party_leanings;

-- Verify new fields in candidacies table
DESCRIBE wp_politeia_candidacies;

-- Verify new fields in jurisdictions table
DESCRIBE wp_politeia_jurisdictions;

-- Verify political_organizations table (renamed from political_parties)
DESCRIBE wp_politeia_political_organizations;
