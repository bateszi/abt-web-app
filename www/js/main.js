const app = {
	'search': (query, sort) => {
		const queryParams = {
			'query': query,
			'sorter': sort
		};

		const queryString = Object.keys(queryParams).map((key) => {
			return encodeURIComponent(key) + '=' + encodeURIComponent(queryParams[key])
		}).join('&');

		window.location.href = "/index.php?" + queryString;
	},
	'searchBySiteName': (linkElm) => {
		app.search('site_name:"'+linkElm.text+'"', 'post_pub_date_sorter desc');
	},
	'searchBySiteType': (linkElm) => {
		app.search('site_type:"'+linkElm.text+'"', 'post_pub_date_sorter desc');
	},
	'searchByMedia': (linkElm) => {
		app.search('"'+linkElm.text+'"', 'post_pub_date_sorter desc');
	}
};

const sortElm = document.getElementById('searchSorter'),
	queryElm = document.getElementById('query');

if (sortElm) {
	sortElm.addEventListener('change', (event) => {
		app.search(queryElm.value, event.target.value);
		return true;
	});
}

document.body.addEventListener('subscribeStatus', (event) => {
	const siteId = event.detail.siteId,
		subType = event.detail.type,
		subscribeBtns = document.querySelectorAll('.subscribeBtn');

	let updatedSubType = '',
		btnText = '',
		stateCssClass = '';

	if (subType === 'a') {
		updatedSubType = 'd';
		btnText = '<i class="fas fa-minus"></i> Unsubscribe';
		stateCssClass = 'unsub';
	} else {
		updatedSubType = 'a';
		btnText = '<i class="fas fa-plus"></i> Subscribe';
		stateCssClass = 'sub';
	}

	if (subscribeBtns.length > 0) {
		subscribeBtns.forEach((subscribeBtnElm) => {
			let subBtnSiteId = parseInt(subscribeBtnElm.getAttribute('data-site-id'));

			if (subBtnSiteId === siteId) {
				subscribeBtnElm.setAttribute('data-type', updatedSubType);
				subscribeBtnElm.innerHTML = btnText;
				subscribeBtnElm.classList.remove('sub', 'unsub');
				subscribeBtnElm.classList.add(stateCssClass);
			}
		});
	}
});

function subscribe(elm, siteId, type) {
	const payload = {
		siteId: siteId,
		type: 	type,
	};

	fetch('/subscribe.php', {
		method: 'POST', // or 'PUT'
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(payload),
	})
		.then(response => response.json())
		.then((data) => {
			if (data.success) {
				const event = new CustomEvent('subscribeStatus', {detail: payload});
				document.body.dispatchEvent(event);
			}
		})
		.catch((error) => {
			console.error('Error:', error);
		});
}

const subscribeBtns = document.querySelectorAll('.subscribeBtn');

if (subscribeBtns.length > 0) {
	subscribeBtns.forEach((subscribeBtnElm) => {
		subscribeBtnElm.addEventListener('click', (event) => {
			let siteId = parseInt(event.target.getAttribute('data-site-id')),
				subType = event.target.getAttribute('data-type');
			subscribe(event.target, siteId, subType);
			event.preventDefault();
			event.stopPropagation();
		});
	})
}