if ! type logdna-agent >/dev/null 2>&1; then
    echo "deb http://repo.logdna.com stable main" | sudo tee /etc/apt/sources.list.d/logdna.list
    wget -O- http://repo.logdna.com/logdna.gpg | sudo apt-key add -
    apt-get update
    apt-get install logdna-agent < "/dev/null" # dev/null required for scripting
    logdna-agent -k {{key}}
    # /var/log is monitored/added by default (recursively), optionally specify more folders here
    update-rc.d logdna-agent defaults
    /etc/init.d/logdna-agent start
fi
