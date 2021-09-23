import React from 'react';
import { Form, Button } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';
import API from '../../../api';
import { Formik, Field } from 'formik';
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
            /*operatingSystem: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.number().required()
            }),
            flavor: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.number().required()
            }),*/
            vnc: Yup.boolean()
        });

        this.state = {
            device: this.props.device,
            operatingSystemOptions: null,
            flavorOptions: null
        };
    }

    componentDidMount()
    {
        console.log("componentDidMount",this.props.device)
        this.api.get('/api/operating-systems')
            .then(response => {
                let operatingSystemOptions = [];
                response.data.forEach(operatingSystem => {
                    operatingSystemOptions.push({
                        ...operatingSystem,
                        value: operatingSystem.id,
                        label: operatingSystem.name,
                    })
                });
                this.setState({operatingSystemOptions});
                return null;
            })
            .then(() => { return this.api.get('/api/flavors') })
            .then(response => {
                let flavorOptions = [];
                response.data.forEach(flavor => {
                    flavorOptions.push({
                        ...flavor,
                        value: flavor.id,
                        label: flavor.name,
                    })
                });
                this.setState({flavorOptions});
                return null;
            });
    }

    componentDidUpdate(prevProps) {
        if(prevProps.device.id != this.props.device.id) {
            this.setState({
                device: this.props.device
            });
        }
        console.log("componentDidUpdate state",this.state.device)

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
                    this.props.onSubmit({
                        id: values.id,
                        name: values.name,
                        brand: values.brand || '',
                        model: values.model || '',
                        operatingSystem: values.operatingSystem.value,
                        flavor: values.flavor.value,
                        vnc: values.vnc
                    });
                }}
                enableReinitialize
                initialValues={{
                    id: this.props.device.id,
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
                    vnc: this.props.device.vnc,
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
                        <Form.Group>
                            <Form.Label>Name</Form.Label>
                            <Form.Control
                                required
                                type="text"
                                name="name"
                                placeholder="Device name"
                                value={values.name}
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
                                value={values.brand}
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
                                value={values.model}
                                onChange={handleChange}
                                isInvalid={!!errors.model}
                            />
                            <Form.Control.Feedback type="invalid">{errors.model}</Form.Control.Feedback>
                        </Form.Group>
                       {/*<Form.Group>
                            <Form.Label>Operating system</Form.Label>
                            <AsyncSelect
                                placeholder="Select an operating system..."
                                value={values.operatingSystem}
                       //         onChange={value => setFieldValue("operatingSystem", value )}
                       //         onBlur={setFieldTouched}
                                error={errors.operatingSystem}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadOperatingSystemOptions}
                                defaultOptions={this.state.operatingSystemOptions}
                            />
                            <Form.Control.Feedback type="invalid">{errors.operatingSystem}</Form.Control.Feedback>
                        </Form.Group>
                        
                        <Form.Group>
                            <Form.Label>Flavor</Form.Label>
                            <AsyncSelect
                                value={values.flavor.label}
                                onChange={value => setFieldValue("flavor", value)}
                                onBlur={setFieldTouched}
                                error={errors.flavor}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadFlavorOptions}
                                defaultOptions={this.state.flavorOptions}
                                placeholder="Select an flavor..."
                            />
                            <Form.Control.Feedback type="invalid">{errors.flavor}</Form.Control.Feedback>
                            </Form.Group>
                        */}
                            <label>
                                <Field type="checkbox" name="vnc" />
                                VNC Access
                            </label>
                        <Button variant="success" type="submit" block {...(dirty || {disabled: true})}>
                            Submit
                        </Button>
                    </Form>
                )}
            </Formik>
        )
    }
}