// Fills the Leaderboard on challenge.html from checkin/leaderboard.json.
// That file is rebuilt by a scheduled job (tools/build_leaderboard.php)
// and contains initials and park names only.
(function () {
  var status = document.getElementById("leaderboard-status");
  var table = document.getElementById("leaderboard-table");
  if (!status || !table) {
    return;
  }

  function cell(row, text) {
    var td = document.createElement("td");
    td.textContent = text;
    row.appendChild(td);
  }

  function formatDate(iso) {
    if (!iso) {
      return "";
    }
    var parts = iso.split("-");
    return Number(parts[1]) + "/" + Number(parts[2]) + "/" + parts[0];
  }

  fetch("/checkin/leaderboard.json", { cache: "no-cache" })
    .then(function (response) {
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      return response.json();
    })
    .then(function (data) {
      var rows = data.rows || [];
      if (rows.length === 0) {
        status.textContent = "No check-ins yet. Scan a QR code at any park to get on the board!";
        return;
      }
      rows.forEach(function (r) {
        var tr = document.createElement("tr");
        cell(tr, String(r.rank));
        cell(tr, r.initials);
        cell(tr, r.count + " - " + r.parks.join(", "));
        cell(tr, formatDate(r.last));
        table.appendChild(tr);
      });
      table.hidden = false;
      status.textContent = "Leaderboard last updated " + new Date(data.updated).toLocaleDateString() +
        ". Participants are ranked by the number of challenge locations visited.";
    })
    .catch(function () {
      status.textContent = "The leaderboard is not available right now.";
    });
})();
