/**
 * Created by nikitich on 05.10.15.
 */

'use strict';

/**
 * Currency rates SPA
 */


var HeaderComponent = React.createClass({
    render: function() {
        return (
            <div className="col-md-12 col-xs-12" id="header">
                <h1>Currency rates</h1>
            </div>
        );
    }
});

var FooterComponent = React.createClass({
    render: function() {
        return (
            <div className="col-md-12">

            </div>
        );
    }
});

var CurrencyRates = React.createClass({
    loadRates: function() {
        $.ajax({
            url: "bin/app.php",
            type: "POST",
            dataType: 'json',
            data: {
                action: "read_rates"
            },
            success: function(response) {
                //console.log(response);
                //console.log(this.state.ratesData);
                var rates = $.map(response.data.rates, function(val, idx){return[val];});
                this.setState({ratesData: rates});
                //console.log(this.state.ratesData);
            }.bind(this),
            error: function(xhr, status, err) {
                console.error(this.props.url, status, err.toString());
            }.bind(this)
        });
    },
    getInitialState: function() {
        return {ratesData: []};
    },
    componentDidMount: function() {
        this.loadRates();
        //setInterval(this.loadRates, this.props.pollInterval);
    },
    render: function() {
        return (
            <div className="row text-center">
                {this.state.ratesData.map(function(currency){
                    return (
                        <CurrencyPlate
                            name={currency.name}
                            sell={currency.sell}
                            buy={currency.buy}
                            spread_a = {currency.spread_a}
                            spread_r = {currency.spread_r}
                            average  = {currency.average}
                        />
                    )
                })}
            </div>
        );
    }
});

var CurrencyPlate = React.createClass({
    render: function() {
        return (
            <div className="col-md-6 col-sm-6 col-xs-12">
                <h1>{this.props.name}</h1>
                <div className="row">
                    <div className="col-md-6 col-sm-6 col-xs-6">
                        <h3>Sell</h3>

                        {this.props.sell}
                    </div>
                    <div className="col-md-6 col-sm-6 col-xs-6">
                        <h3>Buy</h3>
                        {this.props.buy}
                    </div>
                </div>
                <div className="row">
                    <div className="col-md-6 col-xs-6 text-right info-left">
                        Spread:<br/>
                        Average:
                    </div>
                    <div className="col-md-6 col-xs-6 text-left info-right">
                        {this.props.spread_a} RUB ({this.props.spread_r}%)<br />
                        {this.props.average} RUB
                    </div>
                </div>
                <div className="row">
                    <div className="col-md-12 col-xs-12">

                    </div>
                </div>
            </div>
        )
    }
});

var formFields =
    [{
        name: "currency",
        label: "Currency",
        type: "select",
        value: [
            {
                val: "USD",
                text: "USD"
            },
            {
                val: "EUR",
                text: "EUR"
            }
        ]
    }, {
        name: "operation",
        label: "Operation",
        type: "select",
        value: [
            {
                val: "buy",
                text: "BUY"
            },
            {
                val: "sell",
                text: "SELL"
            }
        ]
    }, {
        name: "condition",
        label: "Condition",
        type: "select",
        value: [
            {
                val: "more",
                text: "More than"
            },
            {
                val: "less",
                text: "Less than"
            }
        ]
    }, {
        name: "rate",
        label: "Rate",
        type: "number",
        value: 0.00
    }, {
        name: "email",
        label: "Email",
        type: "email",
        value: ""
    }]
;

var FormField = React.createClass({
    getInitialState: function() {
        return {
            inputElement: <input name={this.props.name} type={this.props.type} className="input_field"/>
        }
    },
    render: function () {

        if (this.props.type == "select")
        {

            this.state.inputElement = (
                    <select name="{this.props.name}" className="input_field">
                        {this.props.value.map(function(field){
                            return (
                            <option value="{field.val}">{field.text}</option>
                            )
                        })}
                    </select>
                )

        }
        else
        {
            //console.log("input");

        }

        return (
            <div>
                <div className="form-group">
                    <div className="row">
                        <div className="col-md-4 col-sm-4 col-xs-4">
                            <label for={this.props.name}>{this.props.label}:</label>
                        </div>
                        <div className="col-md-8  col-sm-8  col-xs-8">
                            {this.state.inputElement}
                            
                        </div>
                    </div>
                </div>
            </div>
        )
    }
});

var TriggerComponent = React.createClass({
    getInitialState: function () {
        return {
            fields: formFields
        }
    },
    render: function () {
        return (
            <div>
                <div className="row">
                    <div className="col-md-4 col-sm-3 hidden-xs">
                    </div>
                    <div className="col-md-4 col-sm-6 col-xs-12 well-lg text-center" id="trigger_form">
                        <h2>Submit triger for notice</h2>
                        <form role="form">
                            {this.state.fields.map(function(field){
                                return (
                                    <FormField
                                        name={field.name}
                                        label={field.label}
                                        type={field.type}
                                        value={field.value}
                                    />
                                )
                            })}
                            <div className="form-group">
                                <div className="col-md-12 col-sx-12">
                                    <input name="set_trigger" type="submit" className="btn btn-default"  value="Submit"/>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div className="col-md-4 col-sm-3 hidden-xs">
                    </div>
                </div>
            </div>

        )
    }
});

var ContentContainerComponent = React.createClass({
    render: function() {
        return (
            <div className="col-md-12" id="content_container">
                <CurrencyRates />
                <TriggerComponent />
            </div>
        );
    }
});

var App = React.createClass({
    render: function() {
        return (
            <div className="container" >
                <div className="row" >
                    <HeaderComponent />
                </div>
                <div className="row">
                    <ContentContainerComponent />
                </div>
                <div className="row">
                    <FooterComponent />
                </div>

            </div>

        );
    }
});

React.render(<App />, document.getElementById('container'));

/**/