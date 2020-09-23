var dbPromise = idb.open('fidelize-pos', 1, function(db) {
	if (!db.objectStoreNames.contains('sin')) {
	 	db.createObjectStore('sin', {keyPath: 'id'});
	}
});

function writeData(table, data) {
	console.log('[IndexedDb storing datas]', table);
	//console.log(table,data);
	return dbPromise
		.then(function(db) {
			var tx = db.transaction(table, 'readwrite');
			var store = tx.objectStore(table);
			store.put(data);
			return tx.complete;
		});
}

function readAllData(table) {
	//console.log("leggo tabella: "+table);
	return dbPromise
		.then(function(db) {
			var tx = db.transaction(table, 'readonly');
			var store = tx.objectStore(table);
			return store.getAll();
		});
}

function clearAllData(table) {
	//console.log("cancello tabella: "+table);
  return dbPromise
    .then(function(db) {
      var tx = db.transaction(table, 'readwrite');
      var store = tx.objectStore(table);
      store.clear();
      return tx.complete;
    });
}

function deleteItemFromData(table, id){
	return dbPromise
		.then(function(db){
			var tx = db.transactions(table, 'readwrite');
			var store = tx.objectStore(table);
			store.delete(id);
			return tx.complete;
		})
		.then(function(){
			console.log('Item deleted');
		});
}

function urlBase64ToUint8Array(base64String) {
  var padding = '='.repeat((4 - base64String.length % 4) % 4);
  var base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  var rawData = window.atob(base64);
  var outputArray = new Uint8Array(rawData.length);

  for (var i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

function dataURItoBlob(dataURI) {
  var byteString = atob(dataURI.split(',')[1]);
  var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0]
  var ab = new ArrayBuffer(byteString.length);
  var ia = new Uint8Array(ab);
  for (var i = 0; i < byteString.length; i++) {
    ia[i] = byteString.charCodeAt(i);
  }
  var blob = new Blob([ab], {type: mimeString});
  return blob;
}

function displayNotification(options){
	if ('serviceWorker' in navigator) {
		//console.log(options);
		// var options = {
		// 	body: 'Hai abilitato con successo il sistema di notifiche push!',
		// 	icon: 'src/images/icons/app-icon-96x96.png',
		// 	//image: 'src/images/icons/app-icon-96x96.png', //immagine nel testo
		// 	dir: 'ltr' , // left to right
		// 	lang: 'it-IT', //BCP 47
		// 	vibrate: [100, 50, 100], //in milliseconds vibra, pausa, vibra, ecc.ecc.
		// 	badge: 'src/images/icons/app-icon-96x96.png', //solo per android è l'icona della notifica
		// 	tag: 'confirm-notification', //tag univoco per le notifiche.
		// 	renotify: true, //connseeo a tag. se è true notifica di nuovo
		// 	actions: [
		// 		{action: 'confirm', title: 'Okay', icon: 'src/images/icons/chk_on.png'},
		// 		//{action: 'cancel', title: 'cancel', icon: 'src/images/icons/chk_off.png'},
		// 	],
		// };

		navigator.serviceWorker.ready
			.then(function(swreg) {
				swreg.showNotification(options.title, options);
			});

	}
}
