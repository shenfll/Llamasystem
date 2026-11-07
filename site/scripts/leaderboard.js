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

  // Website finishers go at the top of the Finishers table (newest first),
  // above the earlier finishers already written in challenge.html.
  function addFinishers(finishers) {
    var finTable = document.getElementById("finishers-table");
    if (!finTable || finishers.length === 0) {
      return;
    }
    var header = finTable.rows[0];
    var insertAfter = header;
    finishers.forEach(function (f) {
      var name = f.mode === "ultra" ? "Ultra LLAMA Challenge" : "LLAMA Challenge";
      var tr = document.createElement("tr");
      [f.initials, formatDate(f.date),
        "As of " + formatDate(f.date) + " " + f.initials + " completed the " + name +
        " by checking in at 20 locations. Congratulations, and thank you for exploring our local treasures!"
      ].forEach(function (text) {
        var th = document.createElement("th");
        th.textContent = text;
        tr.appendChild(th);
      });
      insertAfter.parentNode.insertBefore(tr, insertAfter.nextSibling);
      insertAfter = tr;
    });
  }

  fetch("/checkin/leaderboard.json", { cache: "no-cache" })
    .then(function (response) {
      if (!response.ok) {
        throw new Error("HTTP " + response.status);
      }
      return response.json();
    })
    .then(function (data) {
      addFinishers(data.finishers || []);
      var rows = data.rows || [];
      if (rows.length === 0) {
        status.textContent = "No check-ins yet. Scan a QR code at any park to get on the board!";
        return;
      }
      rows.forEach(function (r, i) {
        var tr = document.createElement("tr");
        cell(tr, String(i + 1));
        cell(tr, String(r.rank));
        cell(tr, r.initials);
        cell(tr, r.count + " - " + r.parks.join(", "));
        cell(tr, formatDate(r.last));
        table.appendChild(tr);
      });
      table.hidden = false;
      status.textContent = rows.length + " participants working on the challenge. Leaderboard last updated " +
        new Date(data.updated).toLocaleDateString() +
        ". Participants are ranked by the number of challenge locations visited; people with the same number share a rank. " +
        "Finishers are listed in the Finishers table above.";
    })
    .catch(function () {
      status.textContent = "The leaderboard is not available right now.";
    });
})();
