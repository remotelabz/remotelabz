import React from 'react';
import SandboxListItem from './SandboxListItem';

const SandboxList = (props) => props.devices.map(
    (device, index) => <SandboxListItem device={device} key={index} index={index} devicesLength={props.devices.length} user={props.user} />
);

export default SandboxList;