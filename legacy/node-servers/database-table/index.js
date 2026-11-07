var fs = require("fs");
var https = require("https");
var child_process = require("child_process");
const options = {
  key: fs.readFileSync("../key.pem"),
  cert: fs.readFileSync("../cert.pem")
}
var convert = {
  clifton_commons: "Clifton Commons",
  boyack: "Boyack Road Park",
  kinns: "Kinns Road Park",
  dwaas: "Dwaas Kill Nature Preserve",
  garnsey: "Garnsey Park",
  george_smith: "George T. Smith Park",
  longkill: "Jewett Park",
  ushers: "Usher's Road State Park",
  ushers_zim: "Usher's Road State Park at Zim Smith Trail",
  skano: "Skano Elementary",
  hayes_liberty: "Hayes Nature Park at Liberty Way",
  hayes_moe: "Hayes Nature Park at Moe Road",
  northwoods_north: "North Woods Preserve at Shadow Wood Way",
  northwoods_water_tower: "North Woods Preserve - near the water tower",
  veterans: "Veterans Memorial Park",
  woodcock: "Woodcock Preserve",
  west_sky: "West Sky Natural Area",
  chango: "Chango Elementary School",
  zim_coons: "Zim Smith Trail at Coons Crossing",
  whipple: "Vischer Ferry Preserve - Whipple Truss Bridge",
  ferry_road: "Vischer Ferry Preserve - Ferry Road",
  clutes: "Vischer Ferry Preserve - Clute's Dry Dock",
  towpathLock7Lookout: "Towpath - Lock 7 Lookout",
  collins: "Collins Park",
  karigon: "Karigon Elementary School",
  okte: "Okte Elementary School",
  orenda: "Orenda Elementary School",
  shatekon: "Shatekon Elementary School",
  tesago: "Tesago Elementary School",
  balsam_moe: "Balsam Way Natural Area at Moe Road",
  balsam_south: "Balsam Way Natural Area at South Entrance",
  vanpatten_west: "Robert Van Patten Trail west end",
  vanpatten_mid: "Robert Van Patten Trail at Carleton Road",
  vanpatten_mid2: "Robert Van Patten Trail at LaCosta Drive",
  vanpatten_east: "Robert Van Patten Trail East End",
  barney: "Barney Road Park",
  halfmoon_town: "Halfmoon Town Park",
  harris: "Harris Park",
  locust_lane: "Locust Lane/Clifton Knoll Park",
  shenantaha: "Shenantaha Creek Park",
  abele: "Abele Memorial Park",
  burning_bush: "Burning Bush Park",
  desrochers: "Peter Desrochers Memorial Trails",
  clifton_gardens: "Clifton Gardens Park",
  mohawk_landing: "Mohawk Landing Nature Preserve",
  indian_mortars: "Indian Mortars Trail",
  ballston_creek: "Ballston Creek Nature Preserve",
  maryjane_row: "Mary Jane Row Dog Park",
  settlers_hill: "Settlers Hill Natural Area",
  summer_hill: "Summer Hill Natural Area",
  calico: "Calico Colony Park",
  bernini: "Bernini Drive Park",
  kimberly: "Kimberly Court Park",
  spirit: "Spirit Park",
  arongen: "Arongen Elementary School"
}
function makehtml(){
  var data = JSON.parse(fs.readFileSync("../database-server/database.json").toString());
  var html = "<!doctype html><html><head><title>database</title><style>table, td, th {border: 1px solid black; border-collapse: collapse;}</style></head><body><table><tr><th>initials</th><th>email</th><th>parks</th></tr>"
  Object.entries(data.users.places).forEach(([key,value]) => {
    html += `<tr><td>${data.users.initials[key]}</td><td>${key}</td><td>${value.join(", ")}</td></tr>`;
  });
  html += "</table>";
  html += "<textarea><table><tr><th>Rank</th><th>Initials</th><th>Locations Visited</th><th>Last Updated</th></tr>";
  var places = Object.entries(data.users.places);
  places = places.filter(function(place){
    return place[1].length < 20;
  });
  places.forEach(function(item){
    item[1] = item[1].filter(function(item2,index){
      return item[1].indexOf(item2) == index;
    });
  });
  places.sort(function(a,b){
    return b[1].length - a[1].length;
  });
  var ranks = [];
  var min = 0;
  var oldrank = Infinity;
  for(var i = 0; i < places.length; i++){
    if(places[i][1].length < oldrank){
      min++;
      oldrank = places[i][1].length;
    }
    ranks.push(min);
  }
  places.forEach(([key,value],index) => {
    html += `<tr><td>`;
    html += ranks[index];
    html += "</td><td>";
    html += data.users.initials[key];
    html += `</td><td>${value.length} - `;
    value.forEach(function(item, index){
      html += convert[item];
      if(index + 1 != value.length){
        html += ", ";
      }
    });
    html += "</td><td>";
    html += child_process.spawnSync("date",["+%D"]).stdout;
    html += "</td></tr>";
  });
  html += "</table></textarea>";
  html += `
    <p>Add to database</p>
    <span>initials</span><input id="initials"><br>
    <span>email</span><input id="email"><br>
    <span>park</span><input id="park"><br>
    <button id="submit">submit</button>
    <script>
      document.querySelector("#submit").onclick = function(){
        var xhr = new XMLHttpRequest();
        xhr.open("POST","https://llamasystem.org:4040");
        xhr.setRequestHeader("Content-Type", "application/xml");
        xhr.send("<user><initials>" + document.querySelector("#initials").value.toUpperCase() + "</initials><email>" + document.querySelector("#email").value.toLowerCase() + "</email><park>" + document.querySelector("#park").value + "</park></user>");
      }
    </script>
  `;
  html += "</body></html>";
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
https.createServer(options, server).listen(3030);
