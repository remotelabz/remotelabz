import React from 'react';
import Select from 'react-select';
import SVG from '../../Display/SVG';
import AsideMenu from './AsideMenu';
import { Button } from 'react-bootstrap';
import LabForm from './../Form/LabForm';

export default function LabAsideMenu(props) {
    return (<AsideMenu onClose={props.onClose}>
        <h2>Lab options</h2>
        <LabForm onSubmit={props.onSubmitLabForm} lab={props.lab}></LabForm>
    </AsideMenu>);
}
