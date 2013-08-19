<?
require 'Slim/Slim.php';

$app = new Slim();
$bdd = new PDO('mysql:host=mysql.iiens.net;dbname=assoce_nightiies', 'assoce_nightiies', 'VwuQREP5JwJQTF5h', array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));



// openid
include('/usr/share/php/openid/consumer/consumer.php');
$consumer   =& AriseOpenID::getInstance();
$openid_url =  !empty($_POST['openid_url']) ? $_POST['openid_url'] : NULL;
$required = array('http://openid.iiens.net/types/identifiant');
$consumer->authenticate($openid_url, $required);
	



// routes

$app->get('/fav', 'fav');
$app->get('/:chan/senders/:sender', 'bySender');
$app->get('/:chan/senders/', 'senders');
$app->get('/:chan/fav', 'fav');
$app->get('/:chan/tags/:tag', 'byTag');
$app->get('/:chan/tags/', 'tags');
$app->get('/:chan/:date', 'day');
$app->get('/:chan/', 'days');
$app->get('/', 'index');

$app->post('/fav', 'favPost');


function days ($chanUrl) {
	$app = Slim::getInstance();
	$chan = '#'.$chanUrl;

	global $bdd;

	include('includes/header.php');
	include('includes/menu.php');
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

	while ($month < 1) {
		$year--;
		$month += 12;
	}

	$day = date('j');
	$dayWeek = date('N', mktime(0, 0, 0, $month, 1, $year)); // jour de la semaine du premier du mois

	// on récupère les jours du mois pour lesquels des liens ont été postés
	$reponse = $bdd->prepare('SELECT DISTINCT DAY(date) FROM playbot WHERE MONTH(date) = '.$month.' AND YEAR(date) = '.$year.' AND chan = :chan ORDER BY date');
	$reponse->bindParam(':chan', $chan, PDO::PARAM_STR);
	$reponse->execute();


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


	$nbr_senders = $bdd->prepare('SELECT sender_irc, COUNT(*) AS nb FROM playbot WHERE chan = :chan GROUP BY sender_irc ORDER BY nb DESC LIMIT 5');
	$nbr_senders->bindParam(':chan', $chan, PDO::PARAM_STR);
	$nbr_senders->execute();

	$nbr_types =  $bdd->prepare('SELECT type, COUNT(*) AS nb FROM playbot WHERE chan = :chan GROUP BY type ORDER BY nb DESC');
	$nbr_types->bindParam(':chan', $chan, PDO::PARAM_STR);
	$nbr_types->execute();

	echo "<h2>Top 5 des posteurs de liens</h2>\n<ul>\n";

	while ($donnees = $nbr_senders->fetch()) {
		echo "<li><strong>$donnees[0] :</strong> $donnees[1]</li>\n";
	}

	echo "</ul>\n<h2>Top des sites</h2>\n<ul>\n";

	while ($donnees = $nbr_types->fetch()) {
		echo "<li><strong>$donnees[0] :</strong> $donnees[1]</li>\n";
	}

	echo <<<INDEXBOT
</ul>
</div>
INDEXBOT;

	$reponse->closeCursor();

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function fav ($chanUrl = '') {
	global $consumer;
	global $bdd;

	include('includes/header.php');
	include('includes/menu.php');

	if ($consumer->isLogged()) {
		$login = $consumer->getSingle('http://openid.iiens.net/types/identifiant');

		// affichage des liens
		$req = $bdd->prepare('SELECT date, type, url, sender_irc, sender, title, id FROM playbot_fav NATURAL JOIN playbot WHERE user = :login');
		$req->bindParam(':login', $login);
		$req->execute();

		echo '<div class="header">Favoris</div>';
		printLinks ($req, $chanUrl);
	

		// code pour irc
		// on regarde si un code existe déjà, sinon on en génère un
		$req = $bdd->prepare('SELECT code FROM playbot_codes WHERE user = :login');
		$req->bindParam(':login', $login);
		$req->execute();

		if ($req->rowCount())
			$code = current($req->fetch());
		else {
			$code = uniqid('PB', true);

			$req = $bdd->prepare('INSERT INTO playbot_codes (user, code) VALUES (:login, :code)');
			$req->bindParam(':login', $login);
			$req->bindParam(':code', $code);
			$req->execute();
		}

echo <<<EOF
<div class='content'>
<br/>
Pour utiliser les favoris avec PlayBot, utiliser la commande suivante : « <em>/query PlayBot $code</em> ».
</div>
EOF;
	}
	else {
		echo <<<FORM
<div class='content'>
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


function day ($chanUrl, $date) {
	global $bdd;
	$chan = '#'.$chanUrl;
	$req = $bdd->prepare('SELECT date, type, url, sender_irc, sender, title, id, GROUP_CONCAT(tag) FROM playbot LEFT OUTER JOIN playbot_tags USING (id) WHERE date = :date AND chan = :chan AND context = 0 GROUP BY id');
	$req->bindParam(':date', $date, PDO::PARAM_STR);
	$req->bindParam(':chan', $chan, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	include('includes/menu.php');
	echo <<<EOF
<div class="header">Log d'activit&eacute; PlayBot</div>
EOF;
	printLinks ($req, $chanUrl);

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function printLinks ($req, $chan) {
	global $consumer;

	echo '<div class="content">';
	echo "<table>\n";
	echo "<tr class='table_header'>\n";
	echo "<td>id</td><td>Lien</td><td>Posteur</td><td>Auteur de la musique</td><td>Titre de la musique</td><td>Favoris</td><td>tags</td>\n";
	while ($donnees = $req->fetch()) {
		echo "<tr>\n";
		echo "<td>".$donnees[6]."</td>\n";
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


		// on affiche les tags
		$tags = explode(',', $donnees[7]);
		$first = true;
		echo '<td>';

		foreach ($tags as $tag) {
			if ($first)
				$first = false;
			else
				echo ' ';

			echo "<a href='/links/$chan/tags/$tag'>$tag</a>";
		}

		echo '</td>';
	}

	echo <<<EOF
</tr>
</table>
EOF;

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function senders ($chanUrl) {
	global $bdd;
	$chan = '#'.$chanUrl;
	$req = $bdd->prepare('SELECT DISTINCT(sender_irc) FROM playbot WHERE chan = :chan ORDER BY sender_irc');
	$req->bindParam(':chan', $chan, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	include('includes/menu.php');
	echo <<<EOF
<div class='content'>
<div class='header'>Liste des posteurs</div>
<ul>
EOF;
	echo '<p>Le regroupement des pseudos sera implémenté plus tard (kikoo Jonas !).</p>';
	echo '<ul>';
	while ($donnees = $req->fetch()) {
		echo '<li><a href="'.$donnees[0].'">'.$donnees[0]."</a></li>\n";
	}

	echo <<<FOOTER
</ul>
</div>
</body>
</html>
FOOTER;
}


function tags ($chanUrl) {
	global $bdd;
	$chan = '#'.$chanUrl;
	$req = $bdd->prepare('SELECT tag, count(*) AS number FROM playbot_tags NATURAL JOIN playbot WHERE chan = :chan AND context = 0 GROUP BY tag ORDER BY tag');
	$req->bindParam(':chan', $chan, PDO::PARAM_STR);
	$req->execute();

	$min = PHP_INT_MAX;
	$max = - PHP_INT_MAX;

	while ($tag = $req->fetch()) {
		if ($tag['number'] < $min) $min = $tag['number'];
		if ($tag['number'] > $max) $max = $tag['number'];
		$tags[] = $tag;
	}
	
	include('includes/header.php');
	include('includes/menu.php');

	echo <<<EOF
<div class='content'>
<div class='header'>Liste des tags</div>
<div class='tags'>
EOF;

	if (!$tags) {
		echo '<p>Y a pas grand chose :(</p>';
		return;
	}

	$min_size = 10;
	$max_size = 100;

	foreach ($tags as $tag) {
		if ($max - $min != 0)
			$tag['size'] = intval($min_size + (($tag['number'] - $min) * (($max_size - $min_size) / ($max - $min))));
		else
			$tag['size'] = $max_size / 2;
		$tags_extended[] = $tag;
	}


	foreach ($tags_extended as $tag)
		echo '<a style="font-size: '.$tag['size'].'px" href="'.$tag[0].'">'.$tag[0]."</a> ";

	echo <<<FOOTER
</div>
</div>
</body>
</html>
FOOTER;
}


function bySender ($chanUrl, $sender) {
	global $bdd;
	$chan = '#'.$chanUrl;
	$req = $bdd->prepare('SELECT date, type, url, sender_irc, sender, title, id, GROUP_CONCAT(tag) FROM playbot LEFT OUTER JOIN playbot_tags USING(id) WHERE sender_irc = :sender AND chan = :chan AND context = 0 GROUP BY id');
	$req->bindParam(':sender', $sender, PDO::PARAM_STR);
	$req->bindParam(':chan', $chan, PDO::PARAM_STR);
	$req->execute();

	include('includes/header.php');
	include('includes/menu.php');
	printLinks ($req, $chanUrl);

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function byTag ($chanUrl, $tag) {
	global $bdd;
	$chan = '#'.$chanUrl;

	$req = $bdd->prepare('SELECT date, type, url, sender_irc, sender, title, id, GROUP_CONCAT(tag) AS tags FROM playbot NATURAL JOIN playbot_tags WHERE chan = :chan AND context = 0 GROUP BY id HAVING tags LIKE (:tag) or tags LIKE (:tagBefore) OR tags LIKE (:tagAfter)');

	$req->bindParam(':tag', $tag, PDO::PARAM_STR);
	$req->bindParam(':chan', $chan, PDO::PARAM_STR);

	$tagBefore = $tag.',%';
	$req->bindParam(':tagBefore', $tagBefore, PDO::PARAM_STR);

	$tagAfter = '%,'.$tag.'%';
	$req->bindParam(':tagAfter', $tagAfter, PDO::PARAM_STR);

	$req->execute();


	include('includes/header.php');
	include('includes/menu.php');
	printLinks ($req, $chanUrl);

	echo <<<FOOTER
</body>
</html>
FOOTER;
}


function index () {
	global $bdd;

	$req = $bdd->prepare('SELECT chan FROM playbot GROUP BY chan');
	$req->execute();
	
	include('includes/header.php');

	echo '<ul>';
	while ($chan = $req->fetch())
		echo "<li><a href='".substr($chan[0],1)."'>$chan[0]</a></li>";

	echo <<<FOOTER
</ul>
</body>
</html>
FOOTER;
}


$app->run();

?>
