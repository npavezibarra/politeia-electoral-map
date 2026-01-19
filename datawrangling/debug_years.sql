SELECT YEAR(e.election_date) as yr, COUNT(*) as count 
FROM wp_politeia_candidacies c 
JOIN wp_politeia_elections e ON c.election_id = e.id 
GROUP BY yr;
