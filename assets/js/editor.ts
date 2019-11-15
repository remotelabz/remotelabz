import { Data, Node, Edge, DataSet, Network, DataInterfaceNodes, DataInterfaceEdges } from 'vis-network';

class Lab
{
    data: any;
    network: Network;

    constructor() {
        this.data = { nodes: new DataSet(), edges: new DataSet([]) };
        this.network = new Network(document.getElementById('labEditor'), this.data, {
            nodes: {
                shape: 'dot',
                borderWidth: 0,
                borderWidthSelected: 10
            },
            edges: {
                width: 3
            },
            manipulation: {
                enabled: true
            }
        })
    }
}

function newLab() {
    return new Lab();
}

function addDevice(lab: Lab) {
    lab.data.nodes.add([{label: 'New device'}]);
    lab.network.fit();
}

function editDevice() {

}

document.addEventListener("DOMContentLoaded", function(event) {
    let lab = new Lab();

    // register events
    let addNodeButtons = document.getElementsByClassName('add-node');
    for (let index = 0; index < addNodeButtons.length; index++) {
        const element = addNodeButtons[index];
        
        element.addEventListener('click', (event) => {
            addDevice(lab);
        })
    }
});