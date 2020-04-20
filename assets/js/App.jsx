// import React, { useState } from 'react';
// import ReactDOM from 'react-dom';
// import { BrowserRouter as Router, Route, Redirect } from 'react-router-dom';
// import { Button } from 'react-bootstrap';

// function PrivateRoute({ children, ...rest }) {
//     return (
//         <Route
//             {...rest}
//             render={({ location }) =>
//                 fakeAuth.isAuthenticated ? (
//                     children
//                 ) : (
//                     <Redirect
//                         to={{
//                             pathname: "/login",
//                             state: { from: location }
//                         }}
//                     />
//                 )
//             }
//         />
//     );
// }

// const App = () =>
// {
//     const [user, setUser] = useState(0);

//     return (
//         <Router>
//             {}
//             <Test message="helllo" />
//         </Router>
//     )
// }

// ReactDOM.render(<App />, document.getElementById('root'));