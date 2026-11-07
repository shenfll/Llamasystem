#!/bin/sh
# Copy of ~/restart/main.sh from the DreamHost account (as of 2026-11-07).
# Started each Node server in a detached `screen` session. Retired: see README.
screen -wipe
killall screen
cd ~/database-server
screen node .
cd ~/database-table
screen node .
cd ~/hits-server
screen node .
cd ~/ultra-database-server
screen node .
cd ~/ultra-database-table
screen node .
cd ~/
screen -ls
