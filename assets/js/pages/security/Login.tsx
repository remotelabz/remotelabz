import React from "react";
import { Link } from 'react-router-dom';
// import Routing from 'fos-js-routing';
import { Card, Form, FormGroup, Button } from 'react-bootstrap';

function Login() {
  return (
    <Card>
      <Form>
        <input type="email" placeholder="email" />
        <input type="password" placeholder="password" />
        <Button>Sign In</Button>
      </Form>
      <Link to="/signup">Don't have an account?</Link>
    </Card>
  );
}

export default Login;