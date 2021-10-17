import React from 'react';
import { Form, Button } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';
import API from '../../../api';
import { Formik, Field, FormikProps, FieldProps} from 'formik';
import * as Yup from 'yup';

export default class NetworkInterfaceForm extends React.Component
{
    constructor(props)
    {
        super(props);

        this.api = API.getInstance();

        this.schema = Yup.object().shape({
            name: Yup.string().required("Name is required."),
            vlan: Yup.number().required(),
        });

        this.state = {
            networkInterface: this.props.networkInterface,
        };
        //console.log("constructor state",this.state.networkInterface)
        //console.log("constructor props",this.props.networkInterface)
    }

    componentDidUpdate(prevProps) {
        if(prevProps.networkInterface.id != this.props.networkInterface.id) {
            this.setState({
                networkInterface: this.props.networkInterface
            });
        }
    }

    render() {
        //console.log("render NetworkInterfaceForm",this.props)
        return (
            <Formik
                validationSchema={this.schema}
                onSubmit={values => {
                    this.props.onSubmit({
                        id: values.id,
                        name: values.name,
                        vlan: values.vlan
                    });
                }}
                enableReinitialize
                initialValues={{
                    id: this.props.networkInterface.id,
                    name: (this.props.networkInterface.name || ''),
                    vlan: (this.props.networkInterface.vlan || '0'),
                }}
            >
                {({
                    handleSubmit,
                    handleChange,
                    values,
                    errors,
                    dirty,
                    setFieldValue,
                    setFieldTouched,
                }) => (
                    <Form noValidate onSubmit={handleSubmit}>
                        <Form.Control type="hidden" name="id" value={values.id} onChange={handleChange} />
                        <Form.Group controlId="name">
                            <Form.Label>Name</Form.Label>
                            <Form.Control
                                required
                                type="text"
                                name="name"
                                placeholder="NIC name"
                                value={values.name}
                                onChange={handleChange}
                                isInvalid={!!errors.name}
                            />
                            <Form.Control.Feedback type="invalid">{errors.name}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group controlId="vlan">
                            <Form.Label>VLAN</Form.Label>
                            <Form.Control
                                required
                                type="number"
                                name="vlan"
                                max="4095"
                                value={values.vlan}
                                onChange={handleChange}
                                isInvalid={!!errors.vlan}
                            />
                            <Form.Text className="text-muted">
                            VLAN number between 1 and 4095. 0 means no VLAN.
                            </Form.Text>
                            <Form.Control.Feedback type="invalid">{errors.vlan}</Form.Control.Feedback>
                        </Form.Group>
                        <Button variant="success" type="submit" block {...(dirty || {disabled: true})}>
                            Submit
                        </Button>
                    </Form>
                )}
            </Formik>
        )
    }
}