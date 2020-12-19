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
