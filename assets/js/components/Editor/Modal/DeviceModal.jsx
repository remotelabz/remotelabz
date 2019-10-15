import React from 'react';
import { Modal, Button, Form, Row, Col, Container } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';

export default class DeviceModal extends React.Component
{
    state = {
        createDevice: false,
        select: {
            selectedOption: null,
            options: [
                { value: 'chocolate', label: 'Chocolate<br>test' },
                { value: 'strawberry', label: 'Strawberry' },
                { value: 'vanilla', label: 'Vanilla' },
            ],
        }
        
    }

    onHide = () => this.props.onHide();
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
                name: this.state.select.selectedOption.label,
            };
            this.props.onSave(device);
        }
    }
    onCreateDevice = () => this.setState({createDevice: true, select: {...this.state.select, selectedOption: null}});

    /**
     * @param {string} inputValue
     * @memberof DeviceModal
     */
    getOptionsRequest = () => {
        return fetch(Routing.generate('api_devices'))
            .then(res => res.json())
            .then(data => {
                console.log(data);
                let options = [];
                data.forEach(device => {
                    options.push({
                        value: device.id,
                        label: device.name,
                    })
                });
                return options;
            })
        ;
    }
    /**
     * @param {string} inputValue
     * @memberof DeviceModal
     */
    loadOptions = (inputValue, callback) => {
        callback(this.getOptionsRequest(inputValue));
    }

    handleChange = selectedOption => {
        this.setState(
            {select: { ...this.state.select, selectedOption: selectedOption }},
            () => console.log(`Option selected:`, this.state.select.selectedOption)
        );
    }

    render() {
        const { selectedOption } = this.state.select;
        const addDeviceForm = (
            <Form id="addDeviceForm">
                <Form.Group>
                    <Form.Label>Name</Form.Label>
                    <Form.Control type="text" placeholder="Name" required name="name" />
                </Form.Group>
            </Form>
        );

        const addDeviceFormFooter = (
            <Modal.Footer>
                <Button variant="secondary" onClick={this.onPrevious}>Previous</Button>
                <Button variant="success" onClick={this.onSave}>Add</Button>
            </Modal.Footer>
        )

        const choiceHandler = (
            <Container>
                <Row>
                    <Col>
                    <AsyncSelect
                        value={selectedOption}
                        onChange={this.handleChange}
                        loadOptions={this.getOptionsRequest}
                        cacheOptions
                        defaultOptions
                        placeholder="Select an existing device..."
                    />
                    </Col>
                </Row>

                <Row>
                    <Col className="editor-choice-separator">
                        <div className="editor-choice-separator-line mr-2"></div>
                        <div className="editor-choice-separator-text">OR</div>
                        <div className="editor-choice-separator-line ml-2"></div>
                    </Col>
                </Row>

                <Row>
                    <Col>
                        <Button variant="success" block size="lg" onClick={this.onCreateDevice}>Create a new device</Button>
                    </Col>
                </Row>
            </Container>
        );

        const selectDeviceFooter = (
            <Modal.Footer>
                <Button variant="success" onClick={this.onSave} disabled={!this.state.createDevice && !this.state.select.selectedOption ? true : false}>Add</Button>
            </Modal.Footer>
        );

        return (
            <Modal size="lg" show={this.props.show} onHide={this.onHide}>
                <Modal.Header closeButton>
                    <Modal.Title>Add a new device</Modal.Title>
                </Modal.Header>

                <Modal.Body>
                    {this.state.createDevice ? addDeviceForm : choiceHandler}
                </Modal.Body>

                {this.state.createDevice ?
                    addDeviceFormFooter :
                    selectDeviceFooter
                }
            </Modal>
        );
    }
}
