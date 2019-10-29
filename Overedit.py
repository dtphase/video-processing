import cv2
from Queue import Queue
from threading import Thread
import numpy as np
import sys
import os
import time
import imutils
import json
import pytesseract

class FileVideoStream:
    def __init__(self, path, queueSize=10000):
		# initialize the file video stream along with the boolean
		# used to indicate if the thread should be stopped or not
        self.stream = cv2.VideoCapture(path)
        self.stopped = False

		# initialize the queue used to store frames read from
		# the video file
        self.Q = Queue(maxsize=queueSize)

    def start(self):
		# start a thread to read frames from the file video stream
		t = Thread(target=self.update, args=())
		t.daemon = True
		t.start()
		return self

    def update(self):
    	# keep looping infinitely
    	while True:
    		# if the thread indicator variable is set, stop the
    		# thread
    		if self.stopped:
    			return
    		# otherwise, ensure the queue has room in it
    		if not self.Q.full():
    			# read the next frame from the file
    			(grabbed, frame) = self.stream.read()
    			# if the `grabbed` boolean is `False`, then we have
    			# reached the end of the video file
                if grabbed == False:
                    # print 'Failed to grab next frame'
                    self.stop()
                    return

                # print self.stream.get(cv2.CAP_PROP_POS_FRAMES)
    			# add the frame to the queue
                #if self.stream.get(cv2.CAP_PROP_POS_FRAMES) % 60 == 0:
                if self.Q.qsize() > 1000:
                    time.sleep(1)

                self.Q.put(frame)

    def read(self):
    	# return next frame in the queue
    	return self.Q.get()

    def more(self):
    	# return True if there are still frames in the queue
    	return self.Q.qsize() > 0

    def stop(self):
    	# indicate that the thread should be stopped
    	self.stopped = True
    	self.stream.release()

def framesToTime(frames):
	seconds = frames / 60
	return "%02d:%02d:%02d" % reduce(lambda ll,b : divmod(ll[0],b) + ll[1:], [(seconds,),60,60])


def find_nearest_lower(array,value):
	narray = np.asarray(array)
	narray = narray[narray<value]
	if narray.size > 0:
		idx = (np.abs(narray-value)).argmin()
		return narray[idx]
	else:
		return -1

# Turn passed args into usable variables
path = sys.argv[1]
windowsPath = "g:" + path[6:]
windowsPath = windowsPath.replace('/', '\\')
#print windowsPath
language = sys.argv[2]
duration = int(sys.argv[3])
client = sys.argv[4].lower()
videoId = int(sys.argv[5])

processPath = '/mnt/g/Essentials/Processing/'
templates = []
if language == 'KR':
	paths = ['dead.png', 'round.png', 'five.png']
else:
	paths = ['equeue.png', 'evictoryb.png', 'etabbed.png']

for p in paths:
	template = cv2.imread(processPath + p)
	template = cv2.cvtColor(template, cv2.COLOR_BGR2GRAY)
	templates.append(template)


def getDeathTimes():
    framesInQueue = [0]
    roundFramesInQueue = [0]
    fiveFramesInQueue = [0]
    deathTimes = []
    roundTimes = []
    fiveTimes = []
    cuts = []

    fvs = FileVideoStream(path).start()
    frames = 0
    while True:
        if fvs.more() == False:
            time.sleep(10.0)
            if fvs.more() == False:
                # print 'No more frames in queue'
                break

        frame = fvs.read()
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        #cv2.imwrite('/mnt/g/Essentials/Processing/Frames/c' + str(frames)+'.png',frame)
        #cv2.imwrite('/mnt/g/Essentials/Processing/Frames/g' + str(frames)+'.png',gray)
        gray = imutils.resize(gray, width=640)
        frames += 1
        res = cv2.matchTemplate(gray, templates[0], cv2.TM_CCOEFF_NORMED)
        min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(res)
        # if frames > 39720:


        # if frames > 48600:
        #     return
        #print(pytesseract.image_to_data(res))

        if max_val > 0.9:
            if frames > framesInQueue[-1] + 1 * 60:
                deathTimes.append([framesInQueue[-1], frames])
            framesInQueue.append(frames)
        else:
            res = cv2.matchTemplate(gray, templates[1], cv2.TM_CCOEFF_NORMED)
            min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(res)
            #print 'Frame:' + str(frames) + ' Val:' + str(max_val)
            if max_val > 0.825:
                if frames > roundFramesInQueue[-1] + 1 * 60:
                    roundTimes.append([roundFramesInQueue[-1], frames])
                roundFramesInQueue.append(frames)
            else:
                res = cv2.matchTemplate(gray, templates[2], cv2.TM_CCOEFF_NORMED)
                min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(res)

                if max_val > 0.9:
                    #print 'Frame:' + str(frames) + ' Val:' + str(max_val) + 'Loc: ' + str(max_loc)
                    #print max_loc[1]
                    if max_loc[1] < 80:
                        if frames > fiveFramesInQueue[-1] + 1 * 60:
                            fiveTimes.append([fiveFramesInQueue[-1], frames])
                        fiveFramesInQueue.append(frames)

    fvs.stop()
    #print framesInQueue
    #print deathTimes
    #print roundTimes
    #print fiveTimes
    for death in deathTimes:
        cutStart = death[1]
        cutEnd = death[1] + 750
        cuts.append(cutStart)
        cuts.append(cutEnd)
    for r in roundTimes:
        cutStart = r[1] - 348
        cuts.append(cutStart)
    for five in fiveTimes:
        cutStart = five[1]
        cuts.append(cutStart)
    return sorted(cuts)


def fetchVictoryTabs(gameTimes):
    	# print gameTimes
    	gameJson = []
    	gameCount = 0
    	cap = cv2.VideoCapture(path)
    	for game in gameTimes:
    		# print game
    		tabbed = None
    		count = 0
    		searchTime = 60 * 60 * 1
    		victory = False
    		cap.set(cv2.CAP_PROP_POS_FRAMES, int(game[1]) - searchTime)
    		while count < searchTime:
    			ret, frame = cap.read()
    			if ret == False:
    				break
    			# print gameCount
    			# print count
    			# print cap.get(cv2.CAP_PROP_POS_FRAMES)
    			gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    			gray = imutils.resize(gray, width=640)

    			res = cv2.matchTemplate(gray, templates[1], cv2.TM_CCOEFF_NORMED)
    			min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(res)
    			if max_val > 0.75:
    				victory = True
    			else:
    				res = cv2.matchTemplate(gray, templates[2], cv2.TM_CCOEFF_NORMED)
    				min_val, max_val, min_loc, max_loc = cv2.minMaxLoc(res)
    				if max_val > 0.85:
    					tabbed = frame
    			count += 1

    		publicDir = '/mnt/c/Users/dt/code/miharo/public/'
    		scoreDir = 'images/scores/' + client.lower() + '/' + str(videoId) + '/'
    		gameCount += 1
    		scoreFile = 'game' + str(gameCount) + '.png'

    		if not os.path.exists(publicDir + scoreDir):
    			os.makedirs(publicDir + scoreDir)

    		if not tabbed is None:
    			if cv2.imwrite(publicDir + scoreDir + scoreFile, tabbed):
    				gameJson.append([framesToTime(game[0]), framesToTime(
    				    game[1]), victory, scoreDir + scoreFile])
    			else:
    				gameJson.append([framesToTime(game[0]), framesToTime(
    				    game[1]), victory, 'images/scores/failed.png'])
    		else:
    			gameJson.append([framesToTime(game[0]), framesToTime(
    			    game[1]), victory, 'images/scores/failed.png'])

    	cap.release()
        return gameJson

games = getDeathTimes()
#json = json.dumps(fetchVictoryTabs(games))
print str(games) + "::" + windowsPath
