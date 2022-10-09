import React, { useState } from 'react';
import Select from 'react-select';
import GroupSelect from './GroupSelect';
import { Alert, Row, Col, Button } from 'react-bootstrap';
import { ValueContainer, Option } from './UserSelect';

export default function GroupImport(props) {
    const [group, setGroup] = useState(null);
    const [selected, setSelected] = useState(null);

    function onGroupChange(value, {action}) {
       /* console.group('Select changed');
        console.log('Value:', value);
        console.log('Action:', action);
        console.groupEnd();
*/
        switch (action) {
            case 'select-option':
                setGroup(value);
                setSelected(value.users.filter(user => props.users.every(u => u.id != user.id)))
                return;
            default:
                return;
        }
    }

    return (<Row><Col>
        <Row>
            <Col>
                <GroupSelect onChange={onGroupChange} />
            </Col>
        </Row>

        {group && <>
        <Row className="my-2">
            <Col lg={9}>
                <Select
                    options={group.users.filter(user => props.users.every(u => u.id != user.id))}
                    isMulti
                    closeMenuOnSelect={false}
                    className='react-select-container'
                    classNamePrefix="react-select"
                    placeholder="Select users to import"
                    name="users[]"
                    components={{ ValueContainer, Option }}
                    getOptionLabel={o => o.name}
                    getOptionValue={o => o.id}
                    value={selected}
                    onChange={(v) => setSelected(v)}
                    noOptionsMessage={() => "No new user to import"}
                />
            </Col>
            <Col>
                <Button type="submit" variant="success" block>Add selected users</Button>
            </Col>
        </Row>

        <Row className="my-2">
            <Col>
                <Alert variant="info">Users will be imported with the <strong>user</strong> role.</Alert>
            </Col>
        </Row>
        </>
        }

        </Col></Row>);
}