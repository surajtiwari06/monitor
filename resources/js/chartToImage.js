// resources/js/chartToImage.js

import ApexCharts from 'apexcharts';
import domtoimage from 'dom-to-image';
import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const customerSites = JSON.parse(document.getElementById('customer-sites').textContent);

    customerSites.forEach(site => {
        const options = {
            series: [{
                name: 'Response time (ms)',
                data: site.chartData,
            }],
            chart: {
                id: `line-datetime-${site.id}`,
                type: 'line',
                height: 400,
                zoom: {
                    autoScaleYaxis: true
                }
            },
            annotations: {
                yaxis: [{
                    y: site.warning_threshold,
                    borderColor: 'orange',
                    label: {
                        show: true,
                        text: 'Threshold',
                        style: {
                            color: "#fff",
                            background: 'orange'
                        }
                    }
                }, {
                    y: site.down_threshold,
                    borderColor: 'red',
                    label: {
                        show: true,
                        text: 'Down',
                        style: {
                            color: "#fff",
                            background: 'red'
                        }
                    }
                }]
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                type: 'datetime',
                min: new Date(site.startTime).getTime(),
                max: new Date(site.endTime).getTime(),
                labels: {
                    datetimeUTC: false,
                },
                title: {
                    text: 'Datetime',
                },
            },
            yaxis: {
                tickAmount: site.y_axis_tick_amount,
                title: {
                    text: 'Milliseconds',
                },
                max: site.y_axis_max,
                min: 0,
            },
            stroke: {
                width: [2]
            },
            tooltip: {
                x: {
                    format: 'dd MMM HH:mm:ss'
                }
            },
        };

        const chart = new ApexCharts(document.createElement('div'), options);
        chart.render().then(() => {
            domtoimage.toPng(chart.w.globals.dom.baseEl)
                .then(dataUrl => {
                    axios.post('/api/save-chart-image', {
                        customerSiteId: site.id,
                        image: dataUrl
                    });
                })
                .catch(error => {
                    console.error('Error generating chart image', error);
                });
        });
    });
});
