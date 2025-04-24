import React, { useState } from 'react';
import InstanceListItem from './InstanceListItem';
import { Button } from 'react-bootstrap';
import SVG from '../Display/SVG';
import InstanceExport from './InstanceExport';
import Remotelabz from '../API';
import Noty from 'noty';

const InstanceList = (props) => {
    console.log("props", props);
    const [showExport, setShowExport] = useState(false);
    let deviceLengthMax = 1;

    console.log("Sandbox", props.isSandbox);
    if (props.isSandbox) {
        for (const instance of props.instances) {
            const device = instance.device;
            console.log("device:", device);
            if (device.name === "Service_sandbox") {
                deviceLengthMax = 2;
            }
        }
    }

    const instancesList = props.instances.map(
        (deviceInstance, index) => (
            <InstanceListItem
                instance={deviceInstance}
                labDeviceLength={deviceLengthMax}
                key={index}
		allInstance={props.instances}
                {...props}
            />
        )
    );

    let allDevicesStopped = true;
    for (const deviceInstance of props.instances) {
        if (deviceInstance.state !== "stopped" && deviceInstance.state !== "exported") {
            allDevicesStopped = false;
            break;
        }
    }

    function exportLabTemplate(labInstance, name) {
        Remotelabz.instances.export(labInstance.uuid, name, "lab")
            .then(() => {
                new Noty({
                    type: 'success',
                    text: 'Instance export requested.',
                    timeout: 5000
                }).show();

                props.onStateUpdate();
                location.href = "/admin/sandbox";
            })
            .catch(() => {
                new Noty({
                    type: 'error',
                    text: 'Error while requesting instance export. Please try again later.',
                    timeout: 5000
                }).show();
            });
    }

    const deviceInstances = props.labInstance?.deviceInstances || [];

    return (
        <>
            {instancesList}
            {(allDevicesStopped && props.isSandbox && deviceInstances.length > deviceLengthMax) &&
                <div className="d-flex justify-content-center mt-2" onClick={() => setShowExport(!showExport)}>
                    <Button variant="default">
                        <SVG name={showExport ? "chevron-down" : "chevron-right"} />
                        Export
                    </Button>
                </div>
            }
            {(allDevicesStopped && showExport) &&
                <InstanceExport
                    instance={props.labInstance}
                    exportTemplate={exportLabTemplate}
                    type="lab"
                />
            }
        </>
    );
};

console.log("test de InstanceList");
export default InstanceList;
