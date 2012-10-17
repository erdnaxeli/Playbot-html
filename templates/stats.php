<?

global $bdd;

$nbr_senders = $bdd->query('SELECT sender_irc, COUNT(*) AS nb FROM playbot GROUP BY sender_irc ORDER BY nb DESC LIMIT 5');
$nbr_types =  $bdd->query('SELECT type, COUNT(*) AS nb FROM playbot GROUP BY type DESC');

echo "<h2>Top 5 des posteurs de liens</h2>\n<ul>\n";

while ($donnees = $nbr_senders->fetch()) {
	echo "<li><strong>$donnees[0] :</strong> $donnees[1]</li>\n";
}

echo "</ul>\n<h2>Top des sites</h2>\n<ul>\n";

while ($donnees = $nbr_types->fetch()) {
	echo "<li><strong>$donnees[0] :</strong> $donnees[1]</li>\n";
}

echo "</ul>\n";

?>
