import React from 'react';
import Noty from 'noty';
import API from '../../api';
import Cropper from 'cropperjs';
import { ButtonToolbar, ButtonGroup, Button } from 'react-bootstrap';

const api = API.getInstance();

export default class PictureEditor extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            isReady: false
        }
    }

    componentDidMount = () => {
        this.props.setUpload(this.upload);

        var reader = new FileReader();
        const parent = this;

        reader.onload = function(event) {
            parent.picture = event.target.result;
            parent.setState({
                isReady: true
            })

            parent.imageElement.addEventListener('ready', function () {
                const containerData = this.cropper.getData();

                this.cropper.zoomTo(0.000001, {
                    x: containerData.width / 2,
                    y: containerData.height / 2,
                });
            });

            parent.cropper = new Cropper(parent.imageElement, {
                viewMode: 1,
                dragMode: "move",
                aspectRatio: 1,
                guides: false,
                center: false,
                scalable: false,
                cropBoxMovable: false,
                cropBoxResizable: false
            });
        }

        reader.readAsDataURL(this.props.file);
    }

    upload = () => {
        const parent = this;

        this.cropper.getCroppedCanvas({
            minWidth: 256,
            minHeight: 256,
            maxWidth: 4096,
            maxHeight: 4096,
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
          }).toBlob((blob) => {
            const formData = new FormData();
            formData.append('picture', blob);

            // Use `jQuery.ajax` method
            api.post(parent.props.endpoint, formData)
            .then(() => {
                console.log('Upload success');
                parent.props.uploadCallback();
            })
            .catch(() => {
                console.log('Upload failed');

                new Noty({
                    type: 'error',
                    text: 'Error uploading your new profile picture. Please try again.',
                    timeout: 5000
                }).show();
            });
        });
    }

    zoomIn = () => {
        this.cropper.zoom(0.1);
    }

    zoomOut = () => {
        this.cropper.zoom(-0.1);
    }

    render() {
        if (!this.props.file) {
            return null;
        }

        return (<>
            <img ref={imageElement => this.imageElement = imageElement} src={this.picture} />

            <ButtonToolbar className="mt-2 justify-content-center">
                <ButtonGroup>
                    <Button variant="info" onClick={this.zoomOut}><i className="fa fa-search-minus" aria-hidden="true"></i></Button>
                    <Button variant="info" onClick={this.zoomIn}><i className="fa fa-search-plus" aria-hidden="true"></i></Button>
                </ButtonGroup>
            </ButtonToolbar>
        </>);
    }
}