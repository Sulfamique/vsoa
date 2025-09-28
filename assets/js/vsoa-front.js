(function () {
function fetchResolve(value) {
return fetch(
VSOA_FRONT_CFG.rest.root.replace(/\/$/, '') + '/' + VSOA_FRONT_CFG.rest.namespace + '/resolve',
{
method: 'POST',
headers: {
'Content-Type': 'application/json',
},
credentials: 'same-origin',
body: JSON.stringify({ data_value: value }),
}
)
.then(function (response) {
if (!response.ok) {
throw new Error('Resolve failed');
}
return response.json();
})
.catch(function (error) {
console.error('VSOA resolve error', error);
throw error;
});
}

document.addEventListener(
'click',
function (event) {
var target = event.target.closest('.vsoa-item');

if (!target) {
return;
}

event.preventDefault();

var value = target.getAttribute('data-value');

if (!value) {
return;
}

fetchResolve(value).then(function (payload) {
if (!payload || !payload.token) {
return;
}

var redirectUrl = window.location.origin + '?vsoa_redirect=' + encodeURIComponent(payload.token);
window.location.assign(redirectUrl);
});
},
false
);
})();
