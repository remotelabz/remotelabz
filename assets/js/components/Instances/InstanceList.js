import React, {useState} from 'react';
import InstanceListItem from './InstanceListItem';
import { Button } from 'react-bootstrap';
import SVG from '../Display/SVG';
import InstanceExport from './InstanceExport';
import Remotelabz from '../API';
import Noty from 'noty';


const InstanceList = (props) => {
    const [showExport, setShowExport] = useState(false)
    //console.log("instancelist");
    //console.log(props);
    let instancesList =  props.instances.map(
        (deviceInstance, index) => <InstanceListItem instance={deviceInstance} key={index} {...props} />
    );
    let allDevicesStopped = true;

    for(var deviceInstance of props.instances) {
        if (deviceInstance.state != "stopped" && deviceInstance != "exported") {
            allDevicesStopped = true;
        }
    }
    function exportLabTemplate(labInstance, name) {
        
        Remotelabz.instances.export(labInstance.uuid, name, "lab").then(() => {
            new Noty({
                type: 'success',
                text: 'Instance export requested.',
                timeout: 5000
            }).show();

            props.onStateUpdate();
            location.href ="/admin/sandbox";
        }).catch(() => {
            new Noty({
                type: 'error',
                text: 'Error while requesting instance export. Please try again later.',
                timeout: 5000
            }).show();
        })
    }

    return (
        <>{instancesList}
            {( allDevicesStopped &&  props.isSandbox && (props.lab.devices.length > 1) ) &&
                <div class="d-flex justify-content-center mt-2" onClick={() => setShowExport(!showExport)}>
                    {showExport ?
                        <Button variant="default"><SVG name="chevron-down"></SVG> Export</Button>
                        :
                        <Button variant="default"><SVG name="chevron-right"></SVG> Export</Button>
                    }
                </div>
            }
            {(allDevicesStopped && showExport) &&
                    <InstanceExport instance={props.labInstance} exportTemplate={exportLabTemplate} type="lab" ></InstanceExport>
            }
        </>
    )
}

export default InstanceList;