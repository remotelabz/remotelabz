import React from 'react';
import { Form } from 'react-bootstrap';

export default class DeviceForm extends React.Component
{
    constructor(props)
    {
        super(props);

        this.state = {
            
        }
    }

    render() {
        return (
            <Form>
                <Form.Group>
                    <Form.Label>Name</Form.Label>
                    <Form.Control required type="text" placeholder="Device name" defaultValue={this.props.device.name}></Form.Control>
                </Form.Group>
                <Form.Group>
                    <Form.Label>Brand</Form.Label>
                    <Form.Control type="text" placeholder="Brand of the device" defaultValue={this.props.device.brand}></Form.Control>
                </Form.Group>
                <Form.Group>
                    <Form.Label>Model</Form.Label>
                    <Form.Control type="text" placeholder="Model of the device" defaultValue={this.props.device.model}></Form.Control>
                </Form.Group>
            </Form>
        )
    }
}