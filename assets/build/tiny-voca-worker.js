/* Tiny Voca RU browser worker and assets, pinned to one upstream revision. */
const modelBase = 'https://huggingface.co/spaces/egnch/tiny-voca-ru-demo/resolve/bf78c265c4cd02a7853d1bcf1d8bcc1fa72aa69e/';
const fetchResource = self.fetch.bind(self);
self.fetch = (resource, options) => fetchResource(
	typeof resource === 'string' && resource.startsWith('assets/') ? new URL(resource, modelBase) : resource,
	options
);
importScripts(modelBase + 'worker.js');
