# Cache Store-API requests
if (req.http.sw-access-key) {
  set req.hash += req.http.sw-access-key;
}

# Consider Shopware http cache cookies
if (req.http.cookie:sw-cache-hash) {
  set req.hash += req.http.cookie:sw-cache-hash;
} elseif (req.http.cookie:sw-currency) {
  set req.hash += req.http.cookie:sw-currency;
}