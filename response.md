# Introduction
  Function Code is implemented by PHP. DataBase is implemented by MongoDB
  
# API Document
  Link: [doc/postMan.json](doc/postMan.json)

# Import Data Commands
  
  pharmacies.json `mongoimport [PATH_TO_FILE] -d test -c pharmacies --jsonArray --drop`
  users.json `mongoimport [PATH_TO_FILE] -d test -c user --jsonArray --drop`
  
# Dockerized
  https://hub.docker.com/repository/docker/a15641854/my-php
  
