var fs = require("fs");
var https = require("https");
const options = {
  key: fs.readFileSync("../key.pem"),
  cert: fs.readFileSync("../cert.pem")
}
function makehtml(){
  var data = JSON.parse(fs.readFileSync("../ultra-database-server/database.json").toString());
  var html = "<!doctype html><html><head><title>database</title><style>table, td, th {border: 1px solid black; border-collapse: collapse;}</style></head><body><table><tr><th>initials</th><th>parks + date</th></tr>"
  Object.entries(data.users.places).forEach(([key,value]) => {
    var text = "";
    Object.entries(value).forEach(([key,value]) => {
      text += key + ", " + value + "; "
    });
    html += `<tr><td>${data.users.initials[key]}</td><td>${text}</td></tr>`
  });
  html += "</table></body></html>"
  return html;
}
function server(req, res){
  if(req.method == "POST"){
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    res.end("ok");
  }
  if(req.method == "GET"){
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    res.write(makehtml());
    res.end();
  }
  if (req.method === "OPTIONS") {
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    res.end("ok");
  }
}
https.createServer(options, server).listen(7070);
