#
#  Factory+ / AMRC Connectivity Stack (ACS) Manager component
#  Copyright 2023 AMRC
#

# bin/bash
kubectl --kubeconfig ./k3s-bmz.yaml get -n fpd-bmz secret manager-keytab -o jsonpath="{.data.client-keytab}" | base64 -d >"./keytab"
