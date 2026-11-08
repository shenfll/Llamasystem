# Publishing changes to llamasystem.org

Changes made in GitHub don't appear on the website until someone publishes them. Publishing means logging in to the DreamHost server and running one command. It takes about two minutes.

This guide is written for Naomi. An agent helping her can walk through it step by step, but **the login must be typed by Naomi**: agents can't log in to the server.

## What you need

- **1Password.** The DreamHost login entry has three things you'll use: the **server name**, the **username** and the **password**. (Ask Bill if you can't find the entry.)
- **A terminal program**:
  - **Windows:** open the Start menu, type **PowerShell**, and open **Windows PowerShell**.
  - **Mac:** open **Terminal** (in Applications → Utilities, or search for it with Spotlight).

## Steps

### 1. Log in to the server

In PowerShell or Terminal, type `ssh`, a space, then the username, an `@` sign, and the server name, all from 1Password. It looks like this, with your real values in place of the words in angle brackets:

```
ssh <username>@<server name>
```

Press Enter.

- **First time only:** you may see "Are you sure you want to continue connecting?". Type `yes` and press Enter.
- When asked for the **password**, copy it from 1Password and paste it: **right-click** in PowerShell, or **Cmd-V** in Terminal. **Nothing appears while you paste or type the password.** That's normal. Press Enter.

You're logged in when you see a prompt ending in `$`, like `[pdx1-...]$`.

### 2. Publish

Copy this line, paste it, and press Enter:

```sh
sh ~/llamasystem-repo/tools/deploy.sh
```

It lists the changes being published, copies the files, and ends with a line starting **`Published`**. For example:

```
Changes being published:
a1b2c3d Add new photos for Harris Park
...
Published a1b2c3d Add new photos for Harris Park to /home/.../llamasystem.org
```

If it says **"Already up to date"**, there was nothing new to publish. That's fine.

### 3. Log out

Type `exit` and press Enter. Close the window.

### 4. Check the website

Open [llamasystem.org](https://www.llamasystem.org/) and look at what changed. Phones may take up to 10 minutes to show the new version. To see it right away, close the browser tab and open the page again.

## If something goes wrong

| What you see | What to do |
|---|---|
| `ssh` is not recognized (Windows) | Open **Settings → Apps → Optional features**, choose **Add a feature**, and install **OpenSSH Client**. Then open a new PowerShell window. |
| "Permission denied" after the password | The password didn't paste correctly. Try again and make sure you copied the whole password from 1Password. After several failures, wait a few minutes. |
| "Connection timed out" | Check your internet connection, and that the server name matches 1Password exactly. |
| The deploy stops with an error, or mentions "git pull" or "local changes" | Don't try to fix it on the server. Copy everything shown in the window and send it to Bill. |
| The website looks broken after publishing | Send Bill the page address and a screenshot. Every published version is saved in GitHub, so it can be put back. |

## Other things you can do while logged in

These are optional and rarely needed.

**See the private check-in report:** no login to the server is needed. Open [llamasystem.org/checkin/admin/report.php](https://www.llamasystem.org/checkin/admin/report.php) and use the **"llama report page"** entry in 1Password.

**Check that the leaderboard is updating:** this shows the last few updates (an empty result means nothing has changed recently, which is normal):

```sh
tail -5 ~/llama-data/cron.log
```

**Add another person who can see the report page:** replace `NAME` with a username for them. It asks you to type their new password twice; save it in 1Password.

```sh
htpasswd ~/.htpasswd-llama NAME
```

Leave everything else on the server to Bill. In particular, don't edit files in `~/llamasystem-repo` directly (make changes in GitHub instead), and don't touch the `mdr` folders, which belong to the Mountain Dog Running website.
