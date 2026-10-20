import React, { useCallback, useState, useRef, useEffect } from 'react';
import { Button } from 'react-bootstrap';
import Noty from 'noty';
import { Formik, Form, Field, useFormikContext } from 'formik';
import { useDropzone } from 'react-dropzone';
import Remotelabz from '../API';
import { toast } from 'react-toastify';
import EveNgImporter from './EveNgImporter';


export default function LabImporter()
{
    const [success, setSuccess] = useState({
        wasCreated: false,
        redirect: ''
    });
    const [fileContent, setFileContent] = useState('');
    const[labTemplateList, setLabTemplateList] = useState([]);
    const [eveNgFile, setEveNgFile] = useState(null);
    //var labTemplatesOptions = [];

    useEffect(() => {
        Remotelabz.labs.getTemplates().then((response)=> {

            const templates = Array.isArray(response.data) ? response.data : [];
            
            const templateOptions = templates.map((template) => {
                return <option id={template.id} key={template.id} value={template.id}>
                    {template.name}
                </option>
            });
        
            setLabTemplateList(templateOptions);
        }).catch(err => {
            console.error('Error fetching templates:', err);
            setLabTemplateList([]);
        });
    }, []);
    
    const onDrop = useCallback(acceptedFiles => {
        acceptedFiles.forEach((file) => {
            // EVE-NG labs (.unl) and their archives (.zip) are handled by the
            // dedicated import wizard instead of the JSON importer.
            if (/\.(unl|zip)$/i.test(file.name)) {
                setEveNgFile(file);
                return;
            }

            const reader = new FileReader()
            reader.onabort = () => console.log('file reading was aborted')
            reader.onerror = () => console.log('file reading has failed')
            reader.onload = (e) => {
                // Do whatever you want with the file contents
                const json = reader.result
                setFileContent(json);
	    }
            reader.readAsText(file)
          })
    }, []);

   useEffect(() => {
	if (fileContent) {
	   formRef.current?.handleSubmit();
	}
   }, [fileContent]);

    const formRef = useRef();
    const { getRootProps, getInputProps, isDragActive } = useDropzone({ onDrop, maxFiles: 1, accept: '.json,.unl,.zip' })


    const validateJson = (value) => {
        let error;
        if (value) {
            try {
                JSON.parse(value)
            } catch (e) {
                error = 'Invalid JSON!';
            }
        }

        return error;
    }

    function submitTemplate() {
        let value = document.getElementById("lab_template_list").value;
        let lab;

        Remotelabz.labs.getTemplate(value).then(response => {
            lab = response.data;

            Remotelabz.labs.import(JSON.stringify(lab)).then(response => {
                //console.log(response.request)
                setSuccess({
                    wasCreated: true,
                    redirect: response.request.responseURL
                });
            }).catch(err => {
                //new Noty({ text: 'An error happened while importing lab. Please try again later or contact an administrator.', type: 'error' }).show();
                toast.error('An error happened while importing lab. Please try again later or contact an administrator.', {
                });
            })
        }).catch(err => {
            //new Noty({ text: 'An error happened while fetching lab. Please try again later.', type: 'error' }).show();
            toast.error('An error happened while fetching lab. Please try again later.', {
                });
        })
    }

    return (
        <div>{success.wasCreated ?
            <div className="text-center">
                <h4>Your lab has been imported.</h4>

                <a href={success.redirect} className="btn btn-primary mt-2">View</a>
            </div>
            :
            eveNgFile ?
            <EveNgImporter
                file={eveNgFile}
                onCancel={() => setEveNgFile(null)}
                onImported={(data) => {
                    if (data?.url) {
                        setSuccess({ wasCreated: true, redirect: data.url });
                    } else {
                        window.location.reload();
                    }
                }}
            />
            :
            <div>
            <section>
                <div {...getRootProps({className: 'dropzone'})}>
                    <input {...getInputProps()} />
                    {isDragActive ?
                        <p>Paste your file here.</p>
                    :
                        <p>Drag 'n' drop a file here, or click to select a file (.json, .unl or .zip).</p>
                    }
                </div>
            </section>

            <div className="lined-separator">OR</div>

            <Formik
                enableReinitialize
                initialValues={{
                    json: fileContent,
                }}
                onSubmit={ (values, actions) => {
                    actions.setSubmitting(true);
                    Remotelabz.labs.import(values.json).then(response => {
                        //console.log(response.request)
                        setSuccess({
                            wasCreated: true,
                            redirect: response.request.responseURL
                        });
                    }).catch(err => {
                        new Noty({ text: 'An error happened while importing lab. Please try again later or contact an administrator.', type: 'error' }).show();
                    }).finally(() => {
                        actions.setSubmitting(false);
                    })
                }}
                innerRef={formRef}
            >
                {({ errors, isSubmitting }) => (
                    <Form>
                        <Field
                            rows={5}
                            name="json"
                            type="text"
                            as="textarea"
                            id="labImportJson"
                            disabled={isSubmitting}
                            validate={validateJson}
                            className="form-control code"
                            placeholder="Paste the lab JSON data here"
                        ></Field>
                        {errors.json && <p className="mt-2 text-danger">{errors.json}</p>}
                        <hr></hr>
                        <Button variant="success" type="submit" disabled={isSubmitting}>Submit</Button>
                    </Form>
                )}
                </Formik>

                <div className="lined-separator">OR</div>
                <section>
                    <p>Select from lab template</p>
                    <select id="lab_template_list" className="form-control">
                        {labTemplateList}
                    </select>
                    <hr></hr>
                    <Button variant="success" type="submit" onClick={submitTemplate}>Submit</Button>
                </section>

            </div>
        }
        </div>
    )
}
