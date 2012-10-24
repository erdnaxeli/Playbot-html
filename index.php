<?
require 'Slim/Slim.php';

$app = new Slim();
$bdd = new PDO('mysql:host=mysql.iiens.net;dbname=assoce_nightiies', 'assoce_nightiies', 'POiREAU.jKNCFfBRq', array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));



// openid
include('/usr/share/php/openid/consumer/consumer.php');
$consumer   =& AriseOpenID::getInstance();
$openid_url =  !empty($_POST['openid_url']) ? $_POST['openid_url'] : NULL;
$required = array('http://openid.iiens.net/types/identifiant');
$consumer->authenticate($openid_url, $required);
	



// routes

$app->get('/senders/:sender', 'bySender');
$app->get('/senders/', 'senders');
$app->get('/fav', 'fav');
$app->get('/:date', 'day');
$app->get('/', 'days');

$app->post('/fav', 'favPost');


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

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function fav () {
	global $consumer;
	global $bdd;

	include('includes/header.php');

	if ($consumer->isLogged()) {
		$login = $consumer->getSingle('http://openid.iiens.net/types/identifiant');

		$req = $bdd->prepare('SELECT date, type, url, sender_irc, sender, title, id FROM playbot_fav NATURAL JOIN playbot WHERE user = :login');
		$req->bindParam(':login', $login);
		$req->execute();

		echo <<<EOF
<div class="header">Favoris</div>
EOF;

		printLinks ($req);
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

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function favPost () {
	global $consumer;
	global $bdd;
	$app = Slim::getInstance();

	if (!$consumer->isLogged()) {
		$app->halt(500, 'User not connected');
		return;
	}

	$login = $consumer->getSingle('http://openid.iiens.net/types/identifiant');

	// on regarde si la vidéo est déjà dans les favoris
	$req = $bdd->prepare('SELECT COUNT(*) FROM playbot_fav WHERE user = :user AND id = :id');
	$req->bindParam(':user', $login);
	$req->bindParam(':id', $_POST['id']);
	$req->execute();
	$isFav = $req->fetch();

	// si oui, on la supprime
	if ($isFav[0]) {
		$req = $bdd->prepare('DELETE FROM playbot_fav WHERE user = :user AND id = :id');
		$req->bindParam(':user', $login);
		$req->bindParam(':id', $_POST['id']);
		$req->execute();

		echo '0';
	}
	// sinon on l'ajoute
	else {
		$req = $bdd->prepare('INSERT INTO playbot_fav(user, id) VALUES(:user, :id)');
		$req->bindParam(':user', $login);
		$req->bindParam(':id', $_POST['id']);
		$req->execute();

		echo '1';
	}
}


function day ($date) {
	global $bdd;
	$req = $bdd->prepare('SELECT * FROM playbot WHERE date = :date');
	$req->bindParam(':date', $date, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	echo <<<EOF
<div class="header">Log d'activit&eacute; PlayBot</div>
EOF;
	printLinks ($req);

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function printLinks ($req) {
	global $consumer;

	echo '<div class="content">';
	echo "<table>\n";
	echo "<tr class='table_header'>\n";
	echo "<td>Lien</td><td>Posteur</td><td>Auteur de la musique</td><td>Titre de la musique</td><td>Favoris</td>\n";
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
		echo <<<EOF
</td>
<td>$donnees[3]</td>
<td>$donnees[4]</td>
<td>$donnees[5]</td>
EOF;

		if ($consumer->isLogged()) {
			global $bdd;
			$login = $consumer->getSingle('http://openid.iiens.net/types/identifiant');

			$req2 = $bdd->prepare('SELECT * FROM playbot_fav WHERE user = :login AND id = :id');
			$req2->bindParam(':login', $login);
			$req2->bindParam(':id', $donnees[6]);
			$req2->execute();

			if ($req2->rowCount())
				echo "<td style='text-align:center'><img onClick='fav(".$donnees[6].")' id='".$donnees[6]."' src='/links/img/star-full.png' /></td>";
			else
				echo "<td style='text-align:center'><img onClick='fav(".$donnees[6].")' id='".$donnees[6]."' src='/links/img/star.png' /></td>";
		}
		else
			echo "<td style='text-align:center'><img onClick='fav(".$donnees[6].")' id='".$donnees[6]."' src='/links/img/star.png' /></td>";

	}

	echo <<<EOF
</tr>
</table>
<br/>\n<div class='retour'><a href='/links'>Retour &agrave; la liste</a></div>\n</div>
EOF;

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function senders () {
	global $bdd;
	$req = $bdd->prepare('SELECT DISTINCT(sender_irc) FROM playbot');
	$req->execute();

	include('includes/header.php');
	echo '<p>Le regroupement des pseudos sera implémenté plus tard (kikoo Jonas !).</p>';
	echo '<ul>';
	while ($donnees = $req->fetch()) {
		echo '<li><a href="'.$donnees[0].'">'.$donnees[0]."</a></li>\n";
	}

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function bySender ($sender) {
	global $bdd;

	$req = $bdd->prepare('SELECT * FROM playbot WHERE sender_irc = :sender');
	$req->bindParam(':sender', $sender, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	printLinks ($req);

	echo <<<FOOTER
</body>
</html>
FOOTER;
}



$app->run();

?>
