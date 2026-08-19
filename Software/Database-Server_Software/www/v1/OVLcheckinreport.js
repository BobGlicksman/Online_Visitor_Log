// checkinreports.js
//
// Creative Commons: Attribution/Share Alike/Non Commercial (cc) 2022 Maker Nexus
// By Jim Schrempp
//
//  Jan 2023:
//      Changed range on weekly graph to start at 20220101 and end 20231221
//  Nov 2022: 
//      Moved JS from the .txt to this file. 


// ----------------
// Utility function to toggle div visibility
function showHideDiv(divId) {
    var div = document.getElementById(divId);
    if (div.style.display === "none") {
        div.style.display = "block";
    } else {
        div.style.display = "none";
    }
}

// ----------------
// ONLOAD
window.onload = function() {
    //dom not only ready, but everything is loaded
    graphdata1 = makeGraphData('graph1dataX', 'graph1dataY');
    var graphlayout1 = {
        autosize: true,
        height: 400,
        title: {
            text:'Monthly Unique Visitors',
            font: {
                size: 18,
                color: '#2d3748',
                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            xref: 'paper',
            x: 0.05,
        },
        plot_bgcolor: '#ffffff',
        paper_bgcolor: '#ffffff',
        margin: {
            l: 50,
            r: 30,
            t: 60,
            b: 80
        },
        xaxis: {
            tickangle: -45,
            tickfont: {
                size: 11,
                color: '#718096'
            }
        },
        yaxis: {
            tickfont: {
                size: 12,
                color: '#718096'
            },
            gridcolor: '#e2e8f0'
        },
        bargap: 0.15
    };
    
    // Update bar color
    graphdata1[0].marker = {
        color: '#667eea',
        line: {
            color: '#5568d3',
            width: 1
        }
    };

    gdiv1 = document.getElementById('graph1');
    Plotly.newPlot(gdiv1, graphdata1, graphlayout1, {responsive: true, displayModeBar: false});

    graphdata2 = makeGraphData('graph2dataX', 'graph2dataY' );
    var graphlayout2 = {
        autosize: true,
        height: 400,
        title: {
            text:'Daily Unique Visitors',
            font: {
                size: 18,
                color: '#2d3748',
                family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            },
            xref: 'paper',
            x: 0.05
        },
        plot_bgcolor: '#ffffff',
        paper_bgcolor: '#ffffff',
        margin: {
            l: 50,
            r: 30,
            t: 60,
            b: 80
        },
        xaxis: {
            autorange: true,
            tickangle: -45,
            dtick: 'M3',
            tickfont: {
                size: 11,
                color: '#718096'
            }
        },
        yaxis: {
            tickfont: {
                size: 12,
                color: '#718096'
            },
            gridcolor: '#e2e8f0'
        },
        bargap: 0.05
    };
    
    // Update bar color
    graphdata2[0].marker = {
        color: '#764ba2',
        line: {
            color: '#5a3a7d',
            width: 1
        }
    };

    gdiv2 = document.getElementById('graph2');
    Plotly.newPlot(gdiv2, graphdata2, graphlayout2, {responsive: true, displayModeBar: false});
}

// --------------
// create the data object for a graph
//   pass in the document elements that hold the values, | delimited
//
function makeGraphData(elementIDX, elementIDY){

    dataX = document.getElementById(elementIDX).textContent;
    dataY = document.getElementById(elementIDY).textContent;
    var x = dataX.split("|");
    var y = dataY.split("|").map(Number);
    var graphData = [
        {
            x:x, 
            y:y, 
            type:"bar"
        }
    ];
    return graphData;

}



