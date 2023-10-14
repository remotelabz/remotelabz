/* UNL App */
var app_main_unl = angular.module("unlMainApp", [
    "ui.router", 
    "ui.bootstrap", 
    "oc.lazyLoad",  
    "ngSanitize"
]); 

/* Configure ocLazyLoader(refer: https://github.com/ocombe/ocLazyLoad) */
app_main_unl.config(['$ocLazyLoadProvider', function($ocLazyLoadProvider) {
    $ocLazyLoadProvider.config({
        // global configs go here
    });
}]);

app_main_unl.config(['$controllerProvider', function($controllerProvider) {
  // this option might be handy for migrating old apps, but please don't use it
  // in new ones!
  $controllerProvider.allowGlobals();
}]);

/* Setup App Main Controller */
app_main_unl.controller('unlMainController', ['$scope', '$rootScope', '$http', '$location', function($scope, $rootScope, $http, $location) {
		$scope.testAUTH = function () {
		$scope.userfolder='none';
		$http.get('/api/auth').then(
			function successCallback(response) {
				if (response.status == '200' && response.statusText == 'OK'){
				$location.path("/main");
				$scope.userfolder = response.data.folder;
				}
			}, 
			function errorCallback(response) {
				if (response.status == '401' && response.statusText == 'Unauthorized'){
				$location.path("/login");}
				else {console.log("Unknown Error. Why did API doesn't respond?")}	
		});
		}
		$scope.testAUTH();
}]);

/* Setup Layout Part - Header */
app_main_unl.controller('HeaderController', ['$scope', '$http', '$location', function($scope, $http, $location) {
       $scope.logout = function() {
			$http.get('/api/auth/logout').then(
			function successCallback(response) {
				if (response.status == '200' && response.statusText == 'OK'){
				$location.path("/login");}
			}, 
			function errorCallback(response) {
				console.log("Unknown Error. Why did API doesn't respond?")	
				$location.path("/login");
			});	
	   } 
}]);



/* Setup Rounting For All Pages */
app_main_unl.config(['$stateProvider', '$urlRouterProvider', function($stateProvider, $urlRouterProvider) {
    // Redirect any unmatched url
    $urlRouterProvider.otherwise("/login");  
    
    $stateProvider

        // LOGIN
        .state('login', {
            url: "/login",
            templateUrl: "/themes/adminLTE/pages/main_layout/login.html",            
            data: {pageTitle: 'Login'}
        })

        // MAIN_LAYOUT
        .state('main', {
            url: "/main",
            templateUrl: "/themes/adminLTE/pages/main_layout/main.html",
            data: {pageTitle: 'Main menu'}
        })
}]);

/* Init global settings and run the app */
app_main_unl.run(["$rootScope", "$state", function($rootScope, $state) {
    $rootScope.$state = $state; // state to be accessed from view
}]);