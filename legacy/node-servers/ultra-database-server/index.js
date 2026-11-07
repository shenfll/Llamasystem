var fs = require("fs");
var https = require("https");
var parseString = require("xml2js").parseString;

const options = {
  key: fs.readFileSync("../key.pem"),
  cert: fs.readFileSync("../cert.pem")
}

fs.readFile("database.json", function(err, data2){
  var data = JSON.parse(data2.toString());
  Object.keys(data.users.initials).forEach(function(item){
    data.users.initials[item] = data.users.initials[item].toUpperCase();
  });
  console.log(data);
  fs.writeFile('database.json', JSON.stringify(data), function (err) {
    console.log('saved');
  });
});

function markitdown(initials,email,park){
  fs.readFile("database.json", function(err, data2){
    var data = JSON.parse(data2.toString());
    if(data.users.places[email]){
      data.users.places[email][park] = Date.now();
    }else{
      data.users.places[email] = {};
      data.users.places[email][park] = Date.now();
      data.users.initials[email] = initials;
    }
    console.log(data);
    fs.writeFile('database.json', JSON.stringify(data), function (err) {
      console.log('saved');
    });
  });
}

function server(req, res){
  if(req.method == "POST"){
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    let body = "";
    req.on("data", function(chunk){
        body += chunk.toString();
    });
    req.on("end", function(){
      var user;
      parseString(body, function (err, result) {
        user = result;
      });
      markitdown(user.user.initials[0],user.user.email[0],user.user.park[0]);
      res.end("ok");
    });
  }
  if(req.method == "GET"){
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    res.end("ok");
  }
  if (req.method === "OPTIONS") {
    res.writeHead(200, {"Access-Control-Allow-Origin": "*","Access-Control-Allow-Methods": "GET,HEAD,OPTIONS,POST,PUT","Access-Control-Allow-Headers": "Origin, X-Requested-With, Content-Type, Accept, Authorization"});
    res.end("ok");
  }
}

https.createServer(options, server).listen(5050);
