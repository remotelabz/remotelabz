
console.log('here')
app_main_unl.controller('loginController', ['$rootScope', '$scope', '$http', function($rootScope, $scope, $http) {
    //$scope.$on('$viewContentLoaded', function() {   
		
        // initialize core components
       $http.get('/api/auth').then(function successCallback(response) {
			console.log('Success')
			console.log(response)
			$http.get("/themes/adminLTE/pages/main_layout/main.html")
				.then(function(response) {
				$('body').removeClass('hold-transition login-page');
				$('body').addClass('hold-transition skin-blue layout-top-nav');
				$scope.html = response.data;
			});
		}, function errorCallback(response) {
			console.log(response.statusText)
		//PUT LOGIN TEMPLATE
			$http.get("/themes/adminLTE/pages/main_layout/login.html")
				.then(function(response) {
				$('body').addClass('hold-transition login-page');
				$scope.html = response.data;
			});
		});
   // });
}]);