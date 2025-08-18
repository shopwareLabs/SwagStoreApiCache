# SwagStoreAPICache: EXPERIMENTAL Reverse-Proxy Cache Plugin for Shopware Store API

SwagStoreApiCache is an **experimental** plugin developed to enhance the performance and scalability of Shopware's [Store API](https://shopware.stoplight.io/docs/store-api/38777d33d92dc-quick-start-guide) by implementing caching at the CDN edge with [Fastly](https://www.fastly.com/). It acts as a reverse-proxy cache specifically tailored to Store API requests, reducing backend load and speeding up response times.

The plugin addresses a key gap in Shopware's Store API ecosystem—the lack of a built-in, advanced edge caching mechanism for dynamic and personalized Store API requests. By integrating with Fastly’s CDN caching, it enables:

- Faster Store API responses by serving cached data from the CDN edge.
- Reduced load on the Shopware backend servers.
- Proper handling of personalized user data via a cache validation mechanism (`sw-cache-hash` cookie) to prevent incorrect or stale information display.

### When to Use It

- If your Shopware storefront relies heavily on the Store API and you experience performance bottlenecks.
- To reduce backend load in high-traffic scenarios.
- When you want to leverage Fastly’s caching capabilities to improve storefront speed without compromising personalized content.


## How to Use It

* Install the plugin in your Shopware environment.
* Configure Fastly with the included configuration snippets provided in `src/Resources/fastly_config`. These configs add cache handling rules specifically for the Store API. The changes from the base config are marked with `# Cache Store-API requests`.
* Adjust your API client(s) to always send the `sw-cache-hash` cookie received in Store API responses in subsequent requests. This ensures that users receive accurate cache-content reflecting their session/user status.

### License

MIT.

### Contributing

Contributions and feedback are welcome to help improve the stability and feature set.