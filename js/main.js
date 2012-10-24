function fav(id) {
	jQuery.ajax({
		type: 'POST', // Le type de ma requete
		url: 'http://nightiies.iiens.net/links/fav', // L'url vers laquelle la requete sera envoyee
		data: {
			id: id // Les donnees que l'on souhaite envoyer au serveur au format JSON
		}, 
		success: function(data, textStatus, jqXHR) {
			if (data == 1)
				$('#' + id).attr('src', '/links/img/star-full.png');
			else
				$('#' + id).attr('src', '/links/img/star.png');
		},
		error: function(jqXHR, textStatus, errorThrown) {
			alert('Erreur. Vérifiez que vous êtes bien connecté.');
		}
	});
}
