import React, { useState } from 'react';
import Remotelabz from '../../API';
import Dropzone from 'react-dropzone';
import EdiText from 'react-editext';

export default function LabEditorHeader({ id, initialName })
{
    const [name, setName] = useState(initialName);
    const [bannerHash, setBannerHash] = useState(Date.now());

    const onNameSave = value => {
        if (value != name) {
            Remotelabz.labs.update({ id, fields: { name: value }})
            .then(() => setName(value))
        }
    }

    const onDrop = acceptedFiles => {
        acceptedFiles.forEach((file) => {
            console.log(file);
            const reader = new FileReader()
      
            reader.onabort = () => console.log('file reading was aborted')
            reader.onerror = () => console.log('file reading has failed')
            reader.onload = () => {
                // Do whatever you want with the file contents
                Remotelabz.labs.uploadBanner(id, file)
                .then(response => {
                    console.log(response.data.url);
                    setBannerHash(Date.now())
                })
            }
            reader.readAsText(file)
          })
    };

    return (
        <div style={{ position: 'relative' }}>
            <Dropzone onDrop={onDrop} maxFiles={1} accept="image/jpg, image/png, image/jpeg">
                {({getRootProps, getInputProps}) => (
                    <section className="mb-3" style={{cursor: 'pointer'}}>
                        <div {...getRootProps()} style={{paddingTop: 25 + '%', position: 'relative'}}>
                            <input {...getInputProps()} />
                            <div style={{
                                top: 0,
                                left: 0,
                                position: 'absolute',
                                width: 100 + '%',
                                height: 100 + '%',
                                filter: 'brightness(50%)',
                                backgroundImage: `url('/labs/${id}/banner?${bannerHash}')`,
                                backgroundSize: 'cover',
                                backgroundPosition: 'center',
                                boxShadow: 'inset 0px -20px 20px 0px rgba(0,0,0,0.5)'
                            }} title="Click to select a new banner or drop your image here"></div>
                        </div>
                    </section>
                )}
            </Dropzone>
            <div style={{ position: 'absolute', bottom: 24 + 'px', left:  24 + 'px', color: 'white', textShadow: '0 0 5px black'}}>
                <EdiText
                    type='text'
                    value={name}
                    onSave={onNameSave}
                    showButtonsOnHover={true}
                    editOnViewClick={true}
                    mainContainerClassName="editor-title"
                    editButtonContent="✎"
                    editButtonClassName="editor-title-edit-button"
                    submitOnEnter
                    inputProps={{
                        className: "editor-title-input"
                    }}
                    saveButtonClassName="editor-save-button"
                    cancelButtonClassName="editor-cancel-button"
                />
            </div>
        </div>
    )
}
