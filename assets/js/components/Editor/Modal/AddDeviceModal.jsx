import React from 'react';
import { Modal, Button, Form, Row, Col, Container } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';
const axios = require('axios').default;

export default class AddDeviceModal extends React.Component
{
    state = {
        createDevice: false,
        canValidate: false,
        selectedOption: null,
    }

    onPrevious = () => this.setState({createDevice: false});
    onSave = () => {
        if (this.state.createDevice) {
            let formData = new FormData(document.getElementById('addDeviceForm'));
            let device = {
                name: formData.get('name')
            };
            this.props.onSave(device);
        } else {
            let device = {
                name: this.state.selectedOption.label,
            };
            this.props.onSave(device);
        }
    }
    onCreateDevice = () => this.setState({createDevice: true, selectedOption: null});

    /**
     * @param {string} inputValue
     * @memberof DeviceModal
     */
    getOptionsRequest = (inputValue) => {
        axios.get('/api/devices', {
            params : {
                search: inputValue,
                template: true
            } 
        })
        .then(response => {
            let options = [];
            response.data.forEach(device => {
                options.push({
                    value: device.id,
                    label: device.name,
                })
            });
            return options;
        });
    }
    /**
     * @param {string} inputValue
     * @memberof DeviceModal
     */
    loadOptions = (inputValue, callback) => {
        callback(this.getOptionsRequest(inputValue));
    }

    handleChange = selectedOption => this.setState({selectedOption});

    render() {
        return (
            <Modal size="lg" show={this.props.show} onHide={this.props.onHide}>
                <Modal.Header closeButton>
                    <Modal.Title>Add a new device</Modal.Title>
                </Modal.Header>

                <Modal.Body>
                    {this.props.children}
                </Modal.Body>

                <Modal.Footer>
                    <Button variant="success" onClick={this.props.onValidate} disabled={!this.state.selectedOption ? true : false}>Add</Button>
                </Modal.Footer>
            </Modal>
        );
    }
}
