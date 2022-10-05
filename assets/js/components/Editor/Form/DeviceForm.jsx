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
            operatingSystem: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.number().required()
            }),
            
            flavor: Yup.object().shape({
                label: Yup.string().required(),
                value: Yup.number().required()
            }),
            nbCpu: Yup.number().min(1).max(12).required(),
            nbCore: Yup.number().min(1).max(4).nullable(true),
            nbSocket: Yup.number().min(1).max(4).nullable(true),
            nbThread: Yup.number().min(1).max(4).nullable(true),
        });

        this.state = {
            device: this.props.device,
            operatingSystemOptions: this.props.device.operatingSystem,
            flavorOptions: this.props.device.flavor,
            controlProtocolOptions: this.props.device.controlProtocolTypes
        };
    }

    componentDidMount()
    {
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
        this.api.get('/api/hypervisor')
            .then(response => {
                let hypervisorOptions = [];
                response.data.forEach(hypervisor => {
                    hypervisorOptions.push({
                        ...hypervisor,
                        value: hypervisor.id,
                        label: hypervisor.name,
                    })
                });
                this.setState({hypervisorOptions});
                return null;
            })
            
        this.api.get('/api/controlProtocolType')
            .then(response => {
                let controlProtocolOptions = [];
                response.data.forEach(controlProtocolTypes => {
                    controlProtocolOptions.push({
                        ...controlProtocolTypes,
                        value: controlProtocolTypes.id,
                        label: controlProtocolTypes.name,
                    })
                });
                this.setState({controlProtocolOptions});
                return null;
            })

        this.api.get('/api/flavors')
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
            })
    }

    componentDidUpdate(prevProps) {
        if(prevProps.device.id != this.props.device.id) {
            this.setState({
                device: this.props.device
            });
        }
        //console.log("componentDidUpdate state",this.state.device)

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

    loadHypervisorOptions = (inputValue) => {
        return this.api.get('/api/hypervisor', {
            params: {
                search: inputValue
            }
        })
        .then(response => {
            let options = [];
            response.data.forEach(hypervisor => {
                options.push({
                    ...hypervisor,
                    value: hypervisor.id,
                    label: hypervisor.name,
                })
            });
            return options;
        });
    }

    loadControlProtocolOptions = (inputValue) => {
        return this.api.get('/api/controlProtocolType', {
            params: {
                search: inputValue
            }
        })
        .then(response => {
            let options = [];
            response.data.forEach(controlProtocolTypes => {
                options.push({
                    ...controlProtocolTypes,
                    value: controlProtocolTypes.id,
                    label: controlProtocolTypes.name,
                })
            });
            return options;
        });
    }


    render() {
        //console.log("this.props.device.controlProtocolTypes",this.props.device.controlProtocolTypes)
        
        return (
            <Formik
                validationSchema={this.schema}
                onSubmit={values => {
                    var result=values.nbThread * values.nbSocket * values.nbCore;
                    if ( result > values.nbCpu) {
                        var nbMaxCpu=result
                    } else {
                        var nbMaxCpu=values.nbCpu
                    }
                    this.props.onSubmit({
                        id: values.id,
                        name: values.name,
                        brand: values.brand || '',
                        model: values.model || '',
                        operatingSystem: values.operatingSystem.value,
                        hypervisor: values.hypervisor.value,
                        flavor: values.flavor.value,
                        nbCpu: nbMaxCpu,
                        nbCore: values.nbCore,
                        nbSocket: values.nbSocket,
                        nbThread: values.nbThread,
                        controlProtocolTypes: values.controlProtocolTypes
                    });
                }}
                enableReinitialize
                initialValues={{
                    id: this.props.device.id,
                    name: this.props.device.name,
                    brand: this.props.device.brand,
                    model: this.props.device.model,
                    operatingSystem: {
                        value: this.props.device.operatingSystem.id || '',
                        label: this.props.device.operatingSystem.name || ''
                    },
                    hypervisor: {
                        value: this.props.device.hypervisor.id || '',
                        label: this.props.device.hypervisor.name || ''
                    },
                    flavor: {
                        value: this.props.device.flavor.id,
                        label: this.props.device.flavor.name
                    },
                    nbCpu: this.props.device.nbCpu,
                    nbCore: this.props.device.nbCore || '',
                    nbSocket: this.props.device.nbSocket || '',
                    nbThread: this.props.device.nbThread || '',
                    controlProtocolTypes: this.props.device.controlProtocolTypes
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
                       <Form.Group>
                            <Form.Label>Operating system</Form.Label>
                            <AsyncSelect
                                placeholder="Select an operating system..."
                                value={values.operatingSystem}
                                onChange={value => setFieldValue("operatingSystem", value )}
                                onBlur={setFieldTouched}
                                error={errors.operatingSystem}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadOperatingSystemOptions}
                                defaultOptions={this.state.operatingSystemOptions}
                            />
                            <Form.Control.Feedback type="invalid">{errors.operatingSystem}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Hypervisor</Form.Label>
                            <AsyncSelect
                                placeholder="Select a hypervisor..."
                                value={values.hypervisor}
                                onChange={value => setFieldValue("hypervisor", value )}
                                onBlur={setFieldTouched}
                                error={errors.hypervisor}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadHypervisorOptions}
                                defaultOptions={this.state.hypervisorOptions}
                            />
                            <Form.Control.Feedback type="invalid">{errors.hypervisor}</Form.Control.Feedback>
                        </Form.Group>
                       
                        <Form.Group>
                            <Form.Label>Flavor</Form.Label>
                            <AsyncSelect
                                placeholder="Select an flavor..."
                                value={values.flavor}
                                onChange={value => setFieldValue("flavor", value)}
                                onBlur={setFieldTouched}
                                error={errors.flavor}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadFlavorOptions}
                                defaultOptions={this.state.flavorOptions}
                            />
                            <Form.Control.Feedback type="invalid">{errors.flavor}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Number CPU</Form.Label>
                            <Form.Control
                                type="text"
                                name="nbCpu"
                                placeholder="Number of CPU (mandatory)"
                                value={values.nbCpu}
                                onChange={handleChange}
                                isInvalid={!!errors.nbCpu}
                            />
                            <Form.Control.Feedback type="invalid">{errors.nbCpu}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Number core</Form.Label>
                            <Form.Control
                                type="text"
                                name="nbCore"
                                placeholder="Number of core (can be null)"
                                value={values.nbCore}
                                onChange={handleChange}
                                isInvalid={!!errors.nbCore}
                            />
                            <Form.Control.Feedback type="invalid">{errors.nbCore}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Number socket</Form.Label>
                            <Form.Control
                                type="text"
                                name="nbSocket"
                                placeholder="Number of socket (can be null)"
                                value={values.nbSocket}
                                onChange={handleChange}
                                isInvalid={!!errors.nbSocket}
                            />
                            <Form.Control.Feedback type="invalid">{errors.nbSocket}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Number Thread</Form.Label>
                            <Form.Control
                                type="text"
                                name="nbThread"
                                placeholder="Number of Thread (can be null)"
                                value={values.nbThread}
                                onChange={handleChange}
                                isInvalid={!!errors.nbThread}
                            />
                            <Form.Control.Feedback type="invalid">{errors.nbThread}</Form.Control.Feedback>
                        </Form.Group>
                        <Form.Group>
                            <Form.Label>Control protocol</Form.Label>
                            <AsyncSelect
                                placeholder="Select a control protocol ..."
                                value={values.controlProtocolTypes}
                                onBlur={setFieldTouched}
                                error={errors.controlProtocolTypes}
                                onChange={value => setFieldValue("controlProtocolTypes", value)}
                                className='react-select-container'
                                classNamePrefix="react-select"
                                loadOptions={this.loadControlProtocolOptions}
                                getOptionLabel={o => o.name}
                                getOptionValue={o => o.id}
                                defaultOptions
                                cacheOptions
                                isMulti
                            />
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