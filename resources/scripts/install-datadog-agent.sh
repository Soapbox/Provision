if ! type datadog-agent >/dev/null 2>&1; then
    DD_API_KEY={{key}} bash -c "$(curl -L https://raw.githubusercontent.com/DataDog/datadog-agent/master/cmd/agent/install_script.sh)"
fi
