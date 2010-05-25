# Copyright 2010 Facebook, Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.


import datetime
import os
import time
import urllib
from google.appengine.ext import webapp
from google.appengine.ext.webapp.util import run_wsgi_app
from google.appengine.ext.webapp import template
from google.appengine.api.urlfetch import fetch

try:
    from urlparse import parse_qs # python 2.6
except ImportError:
    from cgi import parse_qs # python 2.5

import simplejson as json
import conf

class Redirect(Exception):
    def __init__(self, _url):
        self._url = _url

    def url(self):
        return self._url

class APIError(Exception):
    pass

class Handler(webapp.RequestHandler):
    def base_url(self):
        return 'http://'+os.environ['HTTP_HOST']+'/'

    # The URL for accessing the Facebook REST API
    def rest_api_url(self, access_token, method, parameters={}):
        parameters['access_token'] = access_token
        parameters['method'] = method
        parameters['format'] = 'JSON'
        return conf.API_BASE_URL + urllib.urlencode(parameters)

    # Make a call to the Facebook REST API
    def rest_api_call(self, access_token, method, parameters={}):
        url = self.rest_api_url(access_token, method, parameters)
        response = fetch(url)
        if response.status_code != 200:
            raise APIError(str(response.status_code)+': '+url+'\n'+response.content)
        return json.loads(response.content)

    # The URL for accessing the Facebook Graph API
    def graph_api_url(self, access_token, oid, edge=None, parameters={}):
        if access_token:
            parameters['access_token'] = access_token

        return (conf.GRAPH_BASE_URL +
                (str(oid) if oid else '') +
                ('/'+str(edge) if edge else '') +
                '?' + urllib.urlencode(parameters))

    # Make a call to the Facebook Graph API
    def graph_api_call(self, access_token, oid, edge=None, parameters={}):
        url = self.graph_api_url(access_token, oid, edge, parameters)
        response = fetch(url)
        if response.status_code != 200:
            raise APIError(str(response.status_code)+': '+url+'\n'+response.content)
        return json.loads(response.content)

    # Get a valid OAuth 2.0 access token, redirecting to the Facebook OAuth
    # endpoints if necessary
    def access_token(self):
        if self.request.get('access_token'):
            return self.request.get('access_token')

        if not self.request.get('code'): # No code
            raise Redirect(self.graph_api_url(
                    None, 'oauth', 'authorize',
                    {'client_id': conf.APP_ID,
                     'callback': self.base_url(),
                     'scope': 'read_insights'}))

        response = fetch(self.graph_api_url(
                None, 'oauth', 'access_token',
                {'client_id': conf.APP_ID,
                 'callback': self.base_url(),
                 'client_secret': conf.APP_SECRET,
                 'code': self.request.get('code')}))
        if response.status_code != 200: # Expired code
            raise Redirect(self.base_url())
        return parse_qs(response.content)['access_token'][0]

    def index(self, access_token):
        # Get the current user
        user = self.rest_api_call(access_token, 'users.getLoggedInUser')

        # Get the pages and apps the current user owns
        pages = map(
            lambda x: int(x['page_id']),
            self.rest_api_call(
                access_token, 'fql.query',
                {'query':
                     'SELECT page_id FROM page_admin WHERE uid=%d' % user}))
        apps = map(
            lambda x: int(x['application_id']),
            self.rest_api_call(
                access_token, 'fql.query',
                {'query':
                     'SELECT application_id FROM developer WHERE developer_id=%d' % user}))

        # Get page and app profiles
        page_data = self.graph_api_call(
            access_token, None, None, {'ids': ','.join(map(str, pages + apps))})

        self.response.out.write(
            template.render('index.html',
                            {'access_token': access_token,
                             'pages': page_data}))

    def download(self, access_token):
        try:
            date = datetime.datetime.strptime(
                self.request.get('date'),
                '%Y-%m-%d').date() + datetime.timedelta(1)
            # The timedelta(1) is necessary since the Insights API uses end_time
        except ValueError: # Invalid Date
            raise Redirect(self.base_url())

        oid = self.request.get('id')

        # Get Insights data
        insights = self.graph_api_call(access_token, oid, 'insights',
                                       {'since': date, 'until': date+datetime.timedelta(1)})

        # Format as CSV and output
        self.response.headers['Content-Type'] = 'text/csv' if not conf.DEBUG else 'text/plain'
        self.response.out.write('object_id,metric,end_time,period,value\n')
        for metric in insights['data']:
            for row in metric['values']:
                date = datetime.datetime.strptime(
                    row['end_time'], '%Y-%m-%dT%H:%M:%S+0000'
                    ).date() + datetime.timedelta(-1)
                self.response.out.write(
                    '%s,%s,%s,%s,%s\n'
                    % (metric['id'].partition('/')[0], metric['name'], date,
                       metric['period'], row['value']))

    # Handle GET requests
    def get(self):
        try:
            access_token = self.access_token()

            if (self.request.get('id')):
                self.download(access_token)
            else:
                self.index(access_token)

        except Redirect, r:
            self.redirect(r.url())
        except APIError, e:
            if conf.DEBUG:
                self.response.headers['Content-Type'] = 'text/plain'
                self.response.out.write('Error Retrieving Data\n')
                self.response.out.write(str(e))


application = webapp.WSGIApplication([('/', Handler)], debug=True)

def main():
    run_wsgi_app(application)

if __name__ == "__main__":
    main()
