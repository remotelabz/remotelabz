import React from 'react';
import { Form, Button } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';
import API from '../../../api';
import { Formik } from 'formik';
import { useFormik } from 'formik';
import * as Yup from 'yup';

export default class DeviceForm extends React.Component
{
    constructor(props)
    {
        super(props);

        this.api = API.getInstance();

        this.schema = Yup.object().shape({
            name: Yup.string().required("Name is required."),
            brand: Yup.string(),
            model: Yup.string(),
            operatingSystem: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.string().required()
            }),
            flavor: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.string().required()
            })
        });

        this.state = {
          operatingSystemOptions: null,
          flavorOptions: null,
        };
    }

    componentDidMount()
    {
        this.api.get('/api/operating-systems')
            .then(response => {
                let options = [];
                response.data.forEach(operatingSystem => {
                    options.push({
                        ...operatingSystem,
                        value: operatingSystem.id,
                        label: operatingSystem.name,
                    })
                });
                this.setState({operatingSystemOptions: options});
            })
        .then(
            this.api.get('/api/flavors')
            .then(response => {
                let options = [];
                response.data.forEach(flavor => {
                    options.push({
                        ...flavor,
                        value: flavor.id,
                        label: flavor.name,
                    })
                });
                this.setState({flavorOptions: options});
            })
        )

        
        ;
    }

    loadFlavorOptions = (inputValue) => {
        return this.api.get('/api/flavors', {
            params: {
                search: inputValue
            } 
        })
        .then(response => {
            let options = [];
            response.data.forEach(flavor => {
                options.push({
                    ...flavor,
                    value: flavor.id,
                    label: flavor.name,
                })
            });
            return options;
        });
    }

    loadOperatingSystemOptions = (inputValue) => {
        return this.api.get('/api/operating-systems', {
            params: {
                search: inputValue
            } 
        })
        .then(response => {
            let options = [];
            response.data.forEach(operatingSystem => {
                options.push({
                    ...operatingSystem,
                    value: operatingSystem.id,
                    label: operatingSystem.name,
                })
            });
            return options;
        });
    }

    render() {
        return (
            <Formik
                validationSchema={this.schema}
                onSubmit={values => {
                    console.log(values);
                }}
                initialValues={{
                    name: this.props.device.name,
                    brand: this.props.device.brand,
                    model: this.props.device.model,
                    operatingSystem: {
                        value: this.props.device.operatingSystem.id,
                        label: this.props.device.operatingSystem.name
                    },
                    flavor: {
                        value: this.props.device.flavor.id,
                        label: this.props.device.flavor.name
                    },
                }}
            >
                {({
                    handleSubmit,
                    handleChange,
                    handleBlur,
                    values,
                    touched,
                    isValid,
                    errors,
                    setFieldValue,
                    setFieldTouched,
                }) => (
                    <Form noValidate onSubmit={handleSubmit}>
                        <Form.Group>
                            <Form.Label>Name</Form.Label>
                            <Form.Control
                                required
                                type="text"
                                name="name"
                                placeholder="Device name"
                                defaultValue={values.name}
                                onChange={handleChange}
                                isInvalid={!!errors.name}
                            />
                            <Form.Control.Feedback type="invalid">{errors.name}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Brand</Form.Label>
                            <Form.Control
                                type="text"
                                name="brand"
                                placeholder="Brand of the device"
                                defaultValue={values.brand}
                                onChange={handleChange}
                                isInvalid={!!errors.brand}
                            />
                            <Form.Control.Feedback type="invalid">{errors.brand}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                        <Form.Label>Model</Form.Label>
                            <Form.Control
                                type="text"
                                name="model"
                                placeholder="Model of the device"
                                defaultValue={values.model}
                                onChange={handleChange}
                                isInvalid={!!errors.model}
                            />
                            <Form.Control.Feedback type="invalid">{errors.model}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Operating system</Form.Label>
                            <AsyncSelect
                                value={values.operatingSystem}
                                onChange={value => setFieldValue("operatingSystem", value)}
                                onBlur={setFieldTouched}
                                error={errors.operatingSystem}
                                loadOptions={this.loadOperatingSystemOptions}
                                defaultOptions={this.state.operatingSystemOptions}
                                placeholder="Select an operating system..."
                            />
                            <Form.Control.Feedback type="invalid">{errors.operatingSystem}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Flavor</Form.Label>
                            <AsyncSelect
                                value={values.flavor}
                                onChange={value => setFieldValue("flavor", value)}
                                onBlur={setFieldTouched}
                                error={errors.flavor}
                                loadOptions={this.loadFlavorOptions}
                                defaultOptions={this.state.flavorOptions}
                                placeholder="Select an flavor..."
                            />
                            <Form.Control.Feedback type="invalid">{errors.flavor}</Form.Control.Feedback>
                        </Form.Group>
                        <Button variant="success" type="submit">
                            Submit
                        </Button>
                    </Form>
                )}
            </Formik>
        )
    }
}