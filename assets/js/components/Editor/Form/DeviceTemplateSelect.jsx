import React from 'react';
import AsyncSelect from 'react-select/async';
const axios = require('axios').default;

export default class DeviceTemplateSelect extends React.Component
{
    state = {
        selectedOption: null,
    }

    onChange = selectedOption => {
        this.setState({selectedOption});
        //console.log(selectedOption);
        if (this.props.onChange) {
            this.props.onChange(selectedOption);
        }
    }

    loadOptions = (inputValue) => {
        return axios.get('/api/devices', {
            params: {
                search: inputValue,
                template: true
            }
        })
        .then(response => {
            let options = [];
            response.data.forEach(device => {
                options.push({
                    ...device,
                    value: device.id,
                    label: device.name,
                })
            });
            return options;
        });
    }

    render() {
        return (
            <AsyncSelect
                value={this.state.selectedOption}
                onChange={this.onChange}
                className='react-select-container'
                classNamePrefix="react-select"
                loadOptions={this.loadOptions}
                cacheOptions
                defaultOptions
                placeholder="Select a device template..."
            />
        )
    }
}