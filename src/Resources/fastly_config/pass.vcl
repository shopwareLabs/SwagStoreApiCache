# Cache Store-API requests
if (req.http.store-api-post == "1") {
  set bereq.method = "POST";
}