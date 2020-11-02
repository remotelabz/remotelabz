import React from 'react';
import { Form, FormCheck, Button } from 'react-bootstrap';
import AsyncSelect from 'react-select/async';
import API from '../../../api';
import { Formik, Field } from 'formik';
import * as Yup from 'yup';

export default class LabForm extends React.Component
{
    constructor(props)
    {
        super(props);

        this.schema = Yup.object().shape({
            isInternetAuthorized: Yup.boolean(),
        });

        this.state = {
            lab: this.props.lab,
        };
    }

    componentDidUpdate(prevProps) {
        if(prevProps.lab.id != this.props.lab.id) {
            this.setState({
                lab: this.props.lab
            });
        }
    }

    render() {
        return (
            <Formik
                validationSchema={this.schema}
                onSubmit={values => {
                    this.props.onSubmit({
                        id: values.id,
                        isInternetAuthorized: values.isInternetAuthorized,
                    });
                }}
                enableReinitialize
                initialValues={{
                    id: this.props.lab.id,
                    isInternetAuthorized: this.props.lab.isInternetAuthorized,
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
                        <label>
                            <Field type="checkbox" name="isInternetAuthorized" className="mr-2" />
                            Can connect to Internet
                        </label>
                        <Button variant="success" type="submit" block {...(dirty || {disabled: true})}>
                            Save
                        </Button>
                    </Form>
                )}
            </Formik>
        )
    }
}