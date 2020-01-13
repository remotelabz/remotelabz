import React from 'react';
import Select from 'react-select';

export default class InstanceOwnerSelector extends React.Component
{
    state = {
        selectedOption: null,
    }

    onChange = selectedOption => {
        this.setState({selectedOption});
        // console.log(selectedOption);
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
            <Select
                value={this.state.selectedOption}
                onChange={this.onChange}
                className='react-select-container inline'
                classNamePrefix="react-select"
                placeholder="Start for..."
                options={[
                    {label: 'me', value: 'user'},
                    {label: 'group', value: 'group'},
                ]}
            />
        )
    }
}

// ReactDOM.render(
//     <InstanceOwnerSelector />,
//     document.getElementById('instanceOwnerSelector')
// );
