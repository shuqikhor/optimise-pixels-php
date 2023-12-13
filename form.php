<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Optimise Pixels</title>
</head>
<body>
	<style>
		html, body {
			box-sizing: border-box;
			height: 100%;
			margin: 0;
			padding: 1rem;
		}
		#wrapper {
			box-sizing: border-box;
			width: 100%;
			min-height: 100%;
			padding: 1rem;
			border: 1px solid transparent
		}
		#wrapper.border {
			border: 1px dashed #cccccc;
		}
		#result img {
			width: 90px;
			height: auto;
		}
	</style>
	<div id="wrapper">
		<div><input type="file" name="svg" id="input-svg" accept="image/svg+xml"></div>
		<section id="result"></section>
	</div>

	<script>
		const upload = (formData) => {
			fetch('index.php', {
				method: 'POST',
				body: formData,
			}).then(
				response => response.text()
			).then(
				response => {
					document.querySelector('#result').innerHTML = response;
				}
			).catch(
				error => console.error(error)
			)
		}
		document.querySelector('#input-svg').addEventListener('change', e => {
			e.preventDefault();
			e.stopPropagation();
			
			const input = document.querySelector('#input-svg');
			if (!input.files.length) return false;

			const formData = new FormData();
			formData.append('svg', input.files[0]);
			upload(formData);

			return false;
		});

		const dropTarget = document.querySelector('#wrapper');
		['dragenter', 'dragleave', 'dragover'].forEach(
			eventName => dropTarget.addEventListener(eventName, e => {
				e.preventDefault();
				e.stopPropagation();
				dropTarget.classList.toggle('border', eventName != 'dragleave');
			})
		)
		dropTarget.addEventListener('drop', e => {
			e.preventDefault();
			e.stopPropagation();
			dropTarget.classList.toggle('border', false)

			let dt = e.dataTransfer;
			console.log(dt);
			if (!dt.files.length) return false;

			const formData = new FormData();
			formData.append('svg', dt.files[0]);
			upload(formData);
		})
	</script>
</body>
</html>