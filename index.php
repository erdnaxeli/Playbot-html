<?
require 'Slim/Slim.php';

$app = new Slim();
$bdd = new PDO('mysql:host=mysql.iiens.net;dbname=assoce_nightiies', 'assoce_nightiies', 'POiREAU.jKNCFfBRq', array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));


$app->get('/', 'days');
function days () {
	$app = Slim::getInstance();

	global $bdd;

	include('includes/header.php');
	echo <<<INDEXHEAD
<div class="header">Log d'activit&eacute; PlayBot</div>
<div class="content">
INDEXHEAD;



	/***************************
	* Génération du calendrier *
	***************************/

	// décalage (mois précédent : $dif == -1)
	$dif = (isset($_GET['dif'])) ? $_GET['dif'] : 0;

	// on récupère la date actuelle;
	$year = date('Y');
	$month = date('n') + $dif;
	while ($month > 12) {
		$year++;
		$month -= 12;
	}
	$day = date('j');
	$dayWeek = date('N', mktime(0, 0, 0, $month, 1, $year)); // jour de la semaine du premier du mois

	// on récupère les jours du mois pour lesquels des liens ont été postés
	$reponse = $bdd->query('SELECT DISTINCT DAY(date) FROM playbot WHERE MONTH(date) = MONTH(NOW()) + '.$dif.' ORDER BY date');


	// en tête du tableau (mois, année)
	echo "<table class='calendar'><thead><tr><td style='text-align: center' colspan='7'><a href='?dif=". ($dif - 1) ."'><<</a>  $month/$year  <a href='?dif=". ($dif + 1) ."'>>></a></td></tr></thead>\n"; 

	// avant de parcourir les résultats, on se postionne au bon jour de la semaine
	echo '<tr>';
	for ($i=1; $i < $dayWeek; $i++)
		echo '<td></td>';
	
	// on parcours les résultats, on enregistre le jour courant du mois pour combler les trous
	$curDay = 1;
	while ($donnees = $reponse->fetch()) {
		while ($curDay <= $donnees[0]) {
			if ($dayWeek == 1)
				echo "\n</tr>\n</tr>\n";

			if ($curDay == $donnees[0])
				echo "<td><a href='$year-$month-$donnees[0]'>$donnees[0]</a></td>\n";
			else
				echo "<td>$curDay</td>";

			$curDay++;
			$dayWeek++;
			if ($dayWeek > 7)
			       $dayWeek = 1;
		}
	}


	// fin du tableau
	while ($curDay <= date('t', mktime(0, 0, 0, $month, 1, $year))) {
		if ($dayWeek == 1)
			echo "\n</tr>\n</tr>\n";

		echo "<td>$curDay</td>";

		$curDay++;
		$dayWeek++;
		if ($dayWeek > 7)
		       $dayWeek = 1;
	}
	
	if ($dayWeek != 1) 
		for ($i=$dayWeek; $i <= 7; $i++)
			echo '<td></td>';

	echo "</tr></table>\n";

	/*********************
	 * fin du calendrier *
	 *********************/



	$app->render('stats.php');
	echo <<<INDEXBOT
</div>
INDEXBOT;

	$reponse->closeCursor();
}


$app->get('/fav', 'fav');
$app->post('/fav', 'fav');
function fav () {
	include('/usr/share/php/openid/consumer/consumer.php');
	$consumer   =& AriseOpenID::getInstance();
	$openid_url =  !empty($_POST['openid_url']) ? $_POST['openid_url'] : NULL;
	$consumer->setReturnTo('http://nightiies.iiens.net/links/fav');
	$consumer->authenticate($openid_url);
	
	include('includes/header.php');

	if ($consumer->isLogged()) {
		echo 'AHAH';
		$consumer->logout();
	}
	else {
		echo <<<FORM
<div>
	<form method='post' action='/links/fav'>
		Login arise : <input type='text' name='openid_url'>
		<input type='submit'>
	</form>
</div>
FORM;
	}
}


$app->get('/:date', 'day');
function day ($date) {
	global $bdd;
	$req = $bdd->prepare('SELECT * FROM playbot WHERE date = :date');
	$req->bindParam(':date', $date, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	printLinks ($req);
}

function printLinks ($req) {
	echo <<<EOF
<div class="header">Log d'activit&eacute; PlayBot</div>
<div class="content">
EOF;
	echo "<table>\n";
	echo "<tr class='table_header'>\n";
	echo "<td>Lien</td><td>Posteur</td><td>Auteur de la musique</td><td>Titre de la musique</td>\n";
	while ($donnees = $req->fetch()) {
		echo "<tr>\n";
		echo "<td>";
		switch ($donnees[1]) {
			case 'youtube':
				echo "<a href='$donnees[2]'><img alt='youtube' src='/links/img/yt.png' /></a>";
				break;
			case 'soundcloud':
				echo "<a href='$donnees[2]'><img alt='soundcloud' src='/links/img/sc.png' /></a>";
				break;
			case 'mixcloud':
				echo "<a href='$donnees[2]'><img alt='mixcloud' src='/links/img/mc.png' width='40px' /></a>";
				break;
			default:
				echo "<a href='$donnees[2]'>$donnees[1]</a>";
				break;
		}
		echo "</td>\n";
		echo "<td>$donnees[3]</td>\n";
		echo "<td>$donnees[4]</td>\n";
		echo "<td>$donnees[5]</td>\n</tr>\n";
	}

	echo "</table>\n";
	echo "<br/>\n<div class='retour'><a href='/links'>Retour &agrave; la liste</a></div>\n</div>\n";
}


$app->get('/senders/', 'senders');
function senders () {
	global $bdd;
	$req = $bdd->prepare('SELECT DISTINCT(sender_irc) FROM playbot');
	$req->execute();

	include('includes/header.php');
	echo '<ul>';
	while ($donnees = $req->fetch()) {
		echo '<li><a href="'.$donnees[0].'">'.$donnees[0]."</a></li>\n";
	}
}


$app->get('/senders/:sender', 'bySender');
function bySender ($sender) {
	global $bdd;

	$req = $bdd->prepare('SELECT * FROM playbot WHERE sender_irc = :sender');
	$req->bindParam(':sender', $sender, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	printLinks ($req);
}



$app->run();

echo <<<FOOTER
</body>
</html>
FOOTER;

?>
